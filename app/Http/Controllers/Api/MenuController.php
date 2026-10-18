<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Support\ApiResponse;

class MenuController extends Controller
{
    use ApiResponse;

    /** GET /api/menus 全部菜单（用户管理-权限勾选网格的数据源） */
    public function index()
    {
        return $this->ok(
            Menu::orderBy('sort')->get(['id', 'key', 'label', 'icon', 'sort'])
        );
    }
}
