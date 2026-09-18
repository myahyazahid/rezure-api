<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = 'C:/repository/rezure/rezure_websites/blog-data.json';
        if (! file_exists($jsonPath)) {
            return;
        }

        $posts = json_decode(file_get_contents($jsonPath), true);
        if (! is_array($posts)) {
            return;
        }

        foreach ($posts as $p) {
            Blog::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'title' => $p['title'],
                    'excerpt' => $p['excerpt'],
                    'content' => $p['content'],
                    'tags' => $p['tags'] ?? [],
                    'featured_image' => $p['featured_image'] ?? null,
                    'status' => $p['status'] ?? 'published',
                    'published_at' => $p['published_at'] ?? now(),
                    'author_name' => $p['author']['name'] ?? 'Muhammad Yahya Zahid',
                    'author_avatar' => $p['author']['avatar'] ?? 'https://github.com/myahyazahid.png',
                ]
            );
        }
    }
}
