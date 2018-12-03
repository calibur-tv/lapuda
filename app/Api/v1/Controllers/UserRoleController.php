<?php

namespace App\Api\V1\Controllers;

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

        $this->role->set($userId, $roleName);

        return $this->resNoContent();
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

        $this->role->create($name, $desc);

        return $this->resNoContent();
    }

    public function updateRole(Request $request)
    {
        $roleName = $request->get('role_name');
        $name = $request->get('update_name');
        $desc = $request->get('update_desc');

        $this->role->update($roleName, $name, $desc);

        return $this->resNoContent();
    }

    public function destroyRole(Request $request)
    {
        $roleName = $request->get('role_name');

        $this->role->destroy($roleName);

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

        return $this->resOK($all);
    }
}
