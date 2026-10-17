<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| SIPHP market tables (PHASE S)
|--------------------------------------------------------------------------
|
| Implements schema.md §4.12–§4.16 (owner decisions D-S1…D-S7, see
| SIPHP_INTEGRATION_PLAN.md). The module is a NATIVE rebuild with MANUAL
| staff entry — no scraping, no iframe, no data sync with the legacy site.
|
|   - `markets`                — market (pasar) master data
|   - `market_item_categories` — barang pokok / subsidi / penting
|   - `market_items`           — items (barang) priced per market
|   - `market_prices`          — manual price history (market × item × date)
|   - `market_stores`          — retail store directory
|
| No items and no prices are seeded here; the master facts (5 markets + 3
| categories — observed only) live in `MarketMasterSeeder`.
|
| Historical integrity: price rows RESTRICT the deletion of their market/item;
| store rows SET NULL when a market is removed (a store may outlive a market).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('market_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('market_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('market_item_categories')->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->string('unit', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('market_items')->restrictOnDelete();
            $table->decimal('price', 14, 2);
            $table->date('effective_date');
            // Staff member who entered the row (audit trail) — optional so the
            // row survives if that account is ever removed.
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One price per market × item × day (D-S6) — also makes manual
            // re-entry of the same day idempotent at the DB layer.
            $table->unique(['market_id', 'item_id', 'effective_date'], 'market_prices_slot_unique');
            // Public "latest per market" reads.
            $table->index(['market_id', 'effective_date']);
        });

        Schema::create('market_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->nullable()
                ->constrained('markets')->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('opening_hours', 100)->nullable();
            // PUBLIC photo path (D-S7) — always a generated filename.
            $table->string('photo_path', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_stores');
        Schema::dropIfExists('market_prices');
        Schema::dropIfExists('market_items');
        Schema::dropIfExists('market_item_categories');
        Schema::dropIfExists('markets');
    }
};
