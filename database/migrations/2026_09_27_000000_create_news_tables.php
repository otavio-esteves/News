<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('homepage_url');
            $table->string('feed_url')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('fetch_interval_minutes')->default(15);
            $table->timestamp('last_fetched_at')->nullable();
            $table->jsonb('config')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('original_url');
            $table->string('canonical_url')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('fetched_at')->nullable();
            $table->longText('content')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('status')->default(ArticleStatus::Discovered->value);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'published_at']);
            $table->index('status');
            $table->index('content_hash');
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default(StoryStatus::Draft->value);
            $table->jsonb('summary_blocks')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category', 'published_at']);
        });

        Schema::create('article_story', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->foreignId('story_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['article_id', 'story_id']);
            $table->index(['story_id', 'article_id']);
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('story_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_version');
            $table->string('schema_version');
            $table->char('input_hash', 64);
            $table->string('status');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->char('cost_currency', 3)->default('USD');
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index('input_hash');
        });

        Schema::create('story_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->unique()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('category');
            $table->jsonb('summary_blocks');
            $table->foreignId('ai_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('validation_error')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('story_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->string('category');
            $table->jsonb('summary_blocks');
            $table->jsonb('reference_snapshots');
            $table->foreignId('ai_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['story_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_revisions');
        Schema::dropIfExists('story_drafts');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('article_story');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('sources');
    }
};
