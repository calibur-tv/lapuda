<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Role;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    protected $role;

    public function __construct()
    {
        $this->role = new Role();
    }

    public function setRole(Request $request)
    {
        $roleName = $request->get('role_name');
        $userId = $request->get('user_id');

        $id = $this->role->set($userId, $roleName);

        return $this->resCreated($id);
    }

    public function deleteRole(Request $request)
    {
        $roleName = $request->get('role_name');
        $userId = $request->get('user_id');
        
        $this->role->remove($userId, $roleName);
        
        return $this->resNoContent();
    }

    public function clearRole(Request $request)
    {
        $roleName = $request->get('role_name');
        $userId = $request->get('user_id');

        $this->role->clear($userId, $roleName);

        return $this->resNoContent();
    }

    public function createRole(Request $request)
    {
        $name = $request->get('name');
        $desc = $request->get('desc');

        $id = $this->role->create($name, $desc);

        return $this->resCreated($id);
    }

    public function updateRole(Request $request)
    {
        $roleId = $request->get('id');
        $name = $request->get('name');
        $desc = $request->get('desc');

        $this->role->update($roleId, $name, $desc);

        return $this->resNoContent();
    }

    public function destroyRole(Request $request)
    {
        $roleId = $request->get('id');

        $this->role->destroy($roleId);

        return $this->resNoContent();
    }

    public function userRoles(Request $request)
    {
        $userId = $request->get('user_id');

        $roles = $this->role->roles($userId);

        return $this->resOK($roles);
    }

    public function roleUsers(Request $request)
    {
        $roleName = $request->get('role_name');

        $roles = $this->role->users($roleName);

        return $this->resOK($roles);
    }

    public function all()
    {
        $all = $this->role->all();
        $userRepository = new UserRepository();
        $users = [];
        foreach ($all['users'] as $user)
        {
            $item = $userRepository->item($user->user_id);
            $item['role_id'] = $user->role_id;
            $users[] = $item;
        }
        $all['users'] = $users;

        return $this->resOK($all);
    }
}
