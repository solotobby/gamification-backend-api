<?php

namespace App\Services;

use App\Repositories\BlogRepositoryModel;
use App\Services\Providers\SpacesService;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlogService
{
    public function __construct(protected BlogRepositoryModel $repo, protected SpacesService $spaces) {}

    public function getAdminBlogs($request)
    {
        try {
            $filters = $request->only(['status', 'category', 'search', 'per_page']);
            $blogs = $this->repo->getAdminBlogs($filters, $request->query('page'));

            $data = [];
            foreach ($blogs as $blog) {
                $data[] = $this->formatBlogSummary($blog);
            }

            return response()->json([
                'status' => true,
                'message' => 'Blogs retrieved successfully.',
                'data' => $data,
                'pagination' => $this->pagination($blogs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving blogs.', 'error' => $e->getMessage()], 500);
        }
    }

    public function createBlog($request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'excerpt' => 'nullable|string|max:500',
                'content' => 'required|string',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'status' => 'sometimes|in:draft,published',
                'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $coverPath = null;
            if ($request->hasFile('cover_image')) {
                $coverPath = $this->spaces->uploadImage($request->file('cover_image'), 'blogs/covers', watermark: false);
            }

            $status = $validated['status'] ?? 'draft';

            $blog = $this->repo->createBlog(array_merge($validated, [
                'author_id' => auth()->id(),
                'cover_image' => $coverPath,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Blog created successfully.',
                'data' => $this->formatBlog($blog),
            ], 201);
        } catch (Throwable $e) {
            Log::error('Blog creation failed: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error creating blog.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateBlog($request, $id)
    {
        try {
            $blog = $this->repo->getBlogById($id);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'excerpt' => 'nullable|string|max:500',
                'content' => 'sometimes|string',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $this->spaces->uploadImage($request->file('cover_image'), 'blogs/covers', watermark: false);
            }

            $blog = $this->repo->updateBlog($blog, $validated);

            return response()->json([
                'status' => true,
                'message' => 'Blog updated successfully.',
                'data' => $this->formatBlog($blog),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error updating blog.', 'error' => $e->getMessage()], 500);
        }
    }

    public function publishBlog($id)
    {
        try {
            $blog = $this->repo->getBlogById($id);
            $blog->publish();
            return response()->json(['status' => true, 'message' => 'Blog published.', 'data' => $this->formatBlog($blog->fresh())], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error publishing blog.', 'error' => $e->getMessage()], 500);
        }
    }

    public function unpublishBlog($id)
    {
        try {
            $blog = $this->repo->getBlogById($id);
            $blog->unpublish();
            return response()->json(['status' => true, 'message' => 'Blog unpublished.', 'data' => $this->formatBlog($blog->fresh())], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error unpublishing blog.', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteBlog($id)
    {
        try {
            $blog = $this->repo->getBlogById($id);
            $this->repo->deleteBlog($blog);
            return response()->json(['status' => true, 'message' => 'Blog deleted.'], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error deleting blog.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getBlogDetail($id)
    {
        try {
            $blog = $this->repo->getBlogById($id);
            return response()->json(['status' => true, 'message' => 'Blog retrieved.', 'data' => $this->formatBlog($blog)], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Blog not found.'], 404);
        }
    }

    public function getAnalytics($id)
    {
        try {
            return response()->json(['status' => true, 'data' => $this->repo->getAnalytics($id)], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving analytics.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getOverallAnalytics()
    {
        try {
            return response()->json(['status' => true, 'data' => $this->repo->getOverallAnalytics()], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving analytics.'], 500);
        }
    }

    // ─── Public ─────────────────────────────────────────────────────────────

    public function getPublicBlogs($request)
    {
        try {
            $filters = $request->only(['category', 'search', 'per_page']);
            $blogs = $this->repo->getPublicBlogs($filters, $request->query('page'));

            $data = [];
            foreach ($blogs as $blog) {
                $data[] = $this->formatBlogSummary($blog);
            }

            return response()->json([
                'status' => true,
                'message' => 'Blogs retrieved successfully.',
                'data' => $data,
                'pagination' => $this->pagination($blogs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error retrieving blogs.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getPublicBlogBySlug($slug, $request)
    {
        try {
            $blog = $this->repo->getPublicBlogBySlug($slug);

            if (!$blog) {
                return response()->json(['status' => false, 'message' => 'Blog not found.'], 404);
            }

            $this->repo->trackView(
                $blog,
                optional(auth('api')->user())->id,
                $request->ip(),
                $request->header('referer')
            );

            $related = $this->repo->getRelatedBlogs($blog);
            $relatedData = [];
            foreach ($related as $r) {
                $relatedData[] = $this->formatBlogSummary($r);
            }

            return response()->json([
                'status' => true,
                'message' => 'Blog retrieved successfully.',
                'data' => array_merge($this->formatBlog($blog), ['related_blogs' => $relatedData]),
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Blog not found.'], 404);
        }
    }

    // ─── Formatters ─────────────────────────────────────────────────────────

    private function formatBlog($blog): array
    {
        return [
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'excerpt' => $blog->excerpt,
            'content' => $blog->content,
            'cover_image' => $blog->cover_image,
            'category' => $blog->category,
            'tags' => $blog->tags ?? [],
            'status' => $blog->status,
            'views_count' => $blog->views_count,
            'reading_time_minutes' => $blog->reading_time_minutes,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'author' => $blog->author ? ['id' => $blog->author->id, 'name' => $blog->author->name] : null,
            'published_at' => $blog->published_at,
            'created_at' => $blog->created_at,
            'updated_at' => $blog->updated_at,
        ];
    }

    private function formatBlogSummary($blog): array
    {
        return [
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'excerpt' => $blog->excerpt,
            'cover_image' => $blog->cover_image,
            'category' => $blog->category,
            'tags' => $blog->tags ?? [],
            'status' => $blog->status,
            'views_count' => $blog->views_count,
            'reading_time_minutes' => $blog->reading_time_minutes,
            'author' => $blog->author ? ['id' => $blog->author->id, 'name' => $blog->author->name] : null,
            'published_at' => $blog->published_at,
            'created_at' => $blog->created_at,
        ];
    }

    private function pagination($p): array
    {
        return [
            'total' => $p->total(),
            'per_page' => $p->perPage(),
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
        ];
    }
}
