<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'gateway_order_id')) {
                $table->string('gateway_order_id', 100)->nullable()->unique()->after('gateway');
            }

            if (! Schema::hasColumn('payments', 'gateway_payment_id')) {
                $table->string('gateway_payment_id', 100)->nullable()->index()->after('gateway_order_id');
            }
        });

        if (Schema::hasColumn('payments', 'merchant_order_id')) {
            DB::table('payments')
                ->whereNull('gateway_order_id')
                ->update(['gateway_order_id' => DB::raw('merchant_order_id')]);
        }

        if (Schema::hasColumn('payments', 'phonepe_order_id')) {
            DB::table('payments')
                ->whereNull('gateway_payment_id')
                ->update(['gateway_payment_id' => DB::raw('phonepe_order_id')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'gateway_payment_id')) {
                $table->dropColumn('gateway_payment_id');
            }

            if (Schema::hasColumn('payments', 'gateway_order_id')) {
                $table->dropColumn('gateway_order_id');
            }
        });
    }
};
