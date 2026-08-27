<?php

namespace App\Repositories;

use App\Models\Blog;
use App\Models\BlogView;

class BlogRepositoryModel
{
    public function getAdminBlogs(array $filters = [], $page = null)
    {
        return Blog::query()
            ->with('author:id,name')
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn($q, $cat) => $q->where('category', $cat))
            ->when($filters['search'] ?? null, fn($q, $search) =>
                $q->where(fn($sq) => $sq->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")))
            ->latest()
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $page);
    }

    public function createBlog(array $data): Blog
    {
        return Blog::create($data);
    }

    public function getBlogById($id): Blog
    {
        return Blog::withTrashed()->with('author:id,name')->findOrFail($id);
    }

    public function updateBlog(Blog $blog, array $data): Blog
    {
        $blog->update($data);
        return $blog->fresh();
    }

    public function deleteBlog(Blog $blog): void
    {
        $blog->delete();
    }

    public function getPublicBlogs(array $filters = [], $page = null)
    {
        return Blog::query()
            ->published()
            ->with('author:id,name')
            ->when($filters['category'] ?? null, fn($q, $cat) => $q->where('category', $cat))
            ->when($filters['search'] ?? null, fn($q, $search) =>
                $q->where(fn($sq) => $sq->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")))
            ->latest('published_at')
            ->paginate($filters['per_page'] ?? 12, ['*'], 'page', $page);
    }

    public function getPublicBlogBySlug(string $slug): ?Blog
    {
        return Blog::published()->with('author:id,name')->where('slug', $slug)->first();
    }

    public function getRelatedBlogs(Blog $blog, $limit = 3)
    {
        return Blog::published()
            ->where('id', '!=', $blog->id)
            ->where('category', $blog->category)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function trackView(Blog $blog, $viewerUserId = null, $ip = null, $referrer = null): void
    {
        BlogView::create([
            'blog_id' => $blog->id,
            'viewer_user_id' => $viewerUserId,
            'ip_address' => $ip,
            'referrer' => $referrer,
        ]);
        $blog->incrementViews();
    }

    public function getAnalytics($blogId): array
    {
        $blog = Blog::findOrFail($blogId);
        $viewsQuery = BlogView::where('blog_id', $blogId);

        return [
            'total_views' => $blog->views_count,
            'unique_views' => (clone $viewsQuery)->distinct('ip_address')->count('ip_address'),
            'views_last_7_days' => (clone $viewsQuery)->where('created_at', '>=', now()->subDays(7))->count(),
            'views_last_30_days' => (clone $viewsQuery)->where('created_at', '>=', now()->subDays(30))->count(),
            'views_by_day' => (clone $viewsQuery)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')->orderBy('date')->get(),
            'top_referrers' => (clone $viewsQuery)
                ->whereNotNull('referrer')
                ->selectRaw('referrer, COUNT(*) as count')
                ->groupBy('referrer')->orderByDesc('count')->limit(5)->get(),
        ];
    }

    public function getOverallAnalytics(): array
    {
        return [
            'total_blogs' => Blog::count(),
            'published_count' => Blog::where('status', 'published')->count(),
            'draft_count' => Blog::where('status', 'draft')->count(),
            'total_views' => (int) Blog::sum('views_count'),
            'most_viewed' => Blog::orderByDesc('views_count')->limit(5)->get(['id', 'title', 'slug', 'views_count']),
        ];
    }
}
