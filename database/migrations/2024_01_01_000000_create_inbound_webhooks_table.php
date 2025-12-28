<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 50)->index();
            $table->string('event_type', 100)->nullable()->index();
            $table->string('external_id', 255)->nullable()->index(); // ID from the provider
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'created_at']);
            $table->index(['provider', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhooks');
    }
};
