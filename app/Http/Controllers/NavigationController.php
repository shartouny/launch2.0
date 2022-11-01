<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\PyroModels\Navigation;

class NavigationController extends Controller
{
	public function index()
	{
		$navigation = Navigation::getNavigation('main_menu');

		return $navigation;
	}
}
