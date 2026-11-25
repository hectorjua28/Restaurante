<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pendiente')->index();
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('idempotency_key', 64)->unique();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['table_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 120);
            $table->string('station', 20);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->json('modifiers')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'to_status']);
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end');
            $table->unsignedInteger('cash_cents')->default(0);
            $table->unsignedInteger('card_cents')->default(0);
            $table->unsignedInteger('transfer_cents')->default(0);
            $table->unsignedInteger('tips_cents')->default(0);
            $table->unsignedInteger('expected_cash_cents')->default(0);
            $table->unsignedInteger('counted_cash_cents');
            $table->integer('difference_cents');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 20)->index();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('tip_cents')->default(0);
            $table->foreignId('cash_closing_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('order_payment', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->primary(['order_id', 'payment_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('order_payment');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
