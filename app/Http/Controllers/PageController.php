<?php

namespace App\Http\Controllers;

use App\PyroModels\Pages;
use Illuminate\Http\Request;

class PageController extends Controller
{
	public function index()
	{
		$page = Pages::with('page.translation')
		                        ->where('path', '/')
		                        ->first();

		return $page->toJson();
	}

	public function getPage($slug)
	{
		$page = Pages::with('page.translation')
		             ->where('slug', $slug)
		             ->first();

		return $page->toJson();
	}
}
