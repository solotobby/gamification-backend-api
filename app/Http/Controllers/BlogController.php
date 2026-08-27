<?php

namespace App\Http\Controllers;

use App\Services\BlogService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(
        protected BlogService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->getAdminBlogs($request);
    }

    public function store(Request $request)
    {
        return $this->service->createBlog($request);
    }

    public function show($id)
    {
        return $this->service->getBlogDetail($id);
    }

    public function update(Request $request, $id)
    {
        return $this->service->updateBlog($request, $id);
    }

    public function destroy($id)
    {
        return $this->service->deleteBlog($id);
    }

    public function publish($id)
    {
        return $this->service->publishBlog($id);
    }

    public function unpublish($id)
    {
        return $this->service->unpublishBlog($id);
    }

    public function analytics($id)
    {
        return $this->service->getAnalytics($id);
    }

    public function overallAnalytics()
    {
        return $this->service->getOverallAnalytics();
    }
}
