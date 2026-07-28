<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable()->unique();
            $table->string('phone')->nullable()->index();
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('lifetime_value_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Principal');
            $table->string('recipient_name');
            $table->string('phone')->nullable();
            $table->string('street');
            $table->string('number')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state');
            $table->string('postcode', 12);
            $table->text('reference')->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
