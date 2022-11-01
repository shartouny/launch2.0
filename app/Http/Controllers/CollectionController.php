<?php

	namespace App\Http\Controllers;
	use Illuminate\Http\Request;
	use App\PyroModels\Collections;

	class CollectionController extends Controller{
		/**
		 * Show the application dashboard.
		 *
		 * @return \Illuminate\Contracts\Support\Renderable
		 */
		public function index()
		{
			$collections = Collections::index();

			return $collections;
		}

	}