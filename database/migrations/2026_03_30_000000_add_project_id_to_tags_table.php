<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('tags')->orderBy('id')->chunkById(100, function ($tags): void {
            foreach ($tags as $tag) {
                $projectId = DB::table('tag_task')
                    ->join('tasks', 'tasks.id', '=', 'tag_task.task_id')
                    ->where('tag_task.tag_id', $tag->id)
                    ->value('tasks.project_id');

                if ($projectId !== null) {
                    DB::table('tags')->where('id', $tag->id)->update(['project_id' => $projectId]);
                }
            }
        });

        DB::table('tags')->whereNull('project_id')->delete();

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
            $table->unique(['project_id', 'name']);
            $table->unique(['project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'name']);
            $table->dropUnique(['project_id', 'slug']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('slug');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
