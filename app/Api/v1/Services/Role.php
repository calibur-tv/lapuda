<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/12/3
 * Time: 上午8:13
 */

namespace App\Api\V1\Services;


use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Role
{
    protected $role_table = 'roles';
    protected $user_table = 'role_users';

    // 用户是否有权限
    public function has($userId, $roleName)
    {
        $roleId = $this->getRoleIdByName($roleName);

        if (!$roleId)
        {
            return false;
        }

        return (boolean)DB
            ::table($this->user_table)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->count();
    }

    // 用户是否有权限
    public function can($userId, $roleName)
    {
        return $this->has($userId, $roleName);
    }

    // 设置用户权限
    public function set($userId, $roleName)
    {
        $roleId = $this->getRoleIdByName($roleName);

        if (!$roleId)
        {
            return false;
        }

        $hasRole = (boolean)DB
            ::table($this->user_table)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->count();

        if ($hasRole)
        {
            return true;
        }

        DB
            ::table($this->user_table)
            ->insert([
                'user_id' => $userId,
                'role_id' => $roleId
            ]);

        Redis::DEL($this->userRolesCachekey($userId));

        return true;
    }

    // 清除用户某条权限
    public function remove($userId, $roleName)
    {
        $roleId = $this->getRoleIdByName($roleName);

        if (!$roleId)
        {
            return true;
        }

        DB
            ::table($this->user_table)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();

        Redis::DEL($this->userRolesCachekey($userId));

        return true;
    }

    // 清除用户所有权限
    public function clear($userId)
    {
        DB
            ::table($this->user_table)
            ->where('user_id', $userId)
            ->delete();

        Redis::DEL($this->userRolesCachekey($userId));

        return true;
    }

    // 用户的所有权限
    public function roles($userId)
    {
        $repository = new Repository();

        return $repository->Cache($this->userRolesCachekey($userId), function () use ($userId)
        {
            $ids = DB
                ::table($this->user_table)
                ->where('user_id', $userId)
                ->pluck('role_id');

            if (empty($ids))
            {
                return [];
            }

            return DB
                ::table($this->role_table)
                ->whereIn('id', $ids)
                ->pluck('name')
                ->toArray();
        });
    }

    // 权限的所有用户
    public function users($roleName)
    {
        $roleId = $this->getRoleIdByName($roleName);
        if (!$roleId)
        {
            return [];
        }

        return DB
            ::table($this->user_table)
            ->where('role_id', $roleId)
            ->pluck('user_id')
            ->toArray();
    }

    // 创建某个权限
    public function create($roleName, $desc = '')
    {
        $roleId = $this->getRoleIdByName($roleName);
        if (!$roleId)
        {
            return $roleId;
        }

        return DB
            ::table($this->role_table)
            ->insertGetId([
                'name' => $roleName,
                'desc' => $desc
            ]);
    }

    // 更新某个权限
    public function update($roleName, $updatedName, $updatedDesc = '')
    {
        $roleId = $this->getRoleIdByName($roleName);
        if (!$roleId)
        {
            return false;
        }

        DB
            ::table($this->role_table)
            ->where('id', $roleId)
            ->update([
                'name' => $updatedName,
                'desc' => $updatedDesc
            ]);

        return true;
    }

    // 移除某个权限
    public function destroy($roleName)
    {
        $roleId = $this->getRoleIdByName($roleName);
        if (!$roleId)
        {
            return true;
        }

        DB
            ::table($this->user_table)
            ->where('role_id', $roleId)
            ->delete();

        DB
            ::table($this->role_table)
            ->where('id', $roleId)
            ->delete();

        return true;
    }

    // 查看所有用户的权限
    public function all()
    {
        $users = DB
            ::table($this->user_table)
            ->get()
            ->toArray();

        $roles = DB
            ::table($this->role_table)
            ->get()
            ->toArray();

        return [
            'users' => $users,
            'roles' => $roles
        ];
    }

    // 根据权限名拿权限id
    protected function getRoleIdByName($roleName)
    {
        return DB
            ::table($this->role_table)
            ->where('name', $roleName)
            ->pluck('id')
            ->first();
    }

    // 用户的权限缓存key
    protected function userRolesCachekey($userId)
    {
        return 'user_' . $userId . '_all_roles';
    }
}