<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\SubCategory;

class CampaignRepositoryModel
 {
    public function __construct(){

    }

    public function listCategories(){
      return Category::orderBy('name', 'ASC')->get();
    }

    public function listSubCategories($data){
        return SubCategory::where('category_id', $data)->orderBy('name', 'DESC')->get(); //->select(['id', 'amount', 'category_id', 'name', 'usd'])->get();
    }
 }
