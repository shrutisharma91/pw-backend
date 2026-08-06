<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Customer Financing Portal (Screens 13–18)
 * Extends shared tables so Super Admin can later view the same customers/loans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'whatsapp_opt_in')) {
                $table->boolean('whatsapp_opt_in')->default(false);
            }
            if (!Schema::hasColumn('customers', 'dob')) {
                $table->date('dob')->nullable();
            }
            if (!Schema::hasColumn('customers', 'monthly_income')) {
                $table->decimal('monthly_income', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('customers', 'aadhaar_last4')) {
                $table->string('aadhaar_last4', 4)->nullable();
            }
            if (!Schema::hasColumn('customers', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('customers', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 15)->index();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'application_payload')) {
                $table->json('application_payload')->nullable();
            }
            if (!Schema::hasColumn('loan_applications', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable();
            }
            if (!Schema::hasColumn('loan_applications', 'tenure_months')) {
                $table->unsignedSmallInteger('tenure_months')->nullable();
            }
            if (!Schema::hasColumn('loan_applications', 'down_payment')) {
                $table->decimal('down_payment', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('loan_applications', 'emi_amount')) {
                $table->decimal('emi_amount', 15, 2)->nullable();
            }
        });

        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->index();
            }
            if (!Schema::hasColumn('loans', 'loan_application_id')) {
                $table->unsignedBigInteger('loan_application_id')->nullable()->index();
            }
            if (!Schema::hasColumn('loans', 'account_no')) {
                $table->string('account_no')->nullable()->unique();
            }
            if (!Schema::hasColumn('loans', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->index();
            }
            if (!Schema::hasColumn('loans', 'product_name')) {
                $table->string('product_name')->nullable();
            }
            if (!Schema::hasColumn('loans', 'emi_amount')) {
                $table->decimal('emi_amount', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('loans', 'down_payment')) {
                $table->decimal('down_payment', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('loans', 'interest_rate')) {
                $table->decimal('interest_rate', 5, 2)->default(0);
            }
            if (!Schema::hasColumn('loans', 'next_due_date')) {
                $table->date('next_due_date')->nullable();
            }
            if (!Schema::hasColumn('loans', 'installments_paid')) {
                $table->unsignedSmallInteger('installments_paid')->default(0);
            }
            if (!Schema::hasColumn('loans', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
            if (!Schema::hasColumn('loans', 'noc_ref')) {
                $table->string('noc_ref')->nullable();
            }
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->decimal('principal', 15, 2)->default(0);
            $table->decimal('interest', 15, 2)->default(0);
            $table->decimal('total_emi', 15, 2)->default(0);
            $table->string('status')->default('UPCOMING'); // PAID, UPCOMING, OVERDUE
            $table->string('utr')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('customer_otps');

        Schema::table('loans', function (Blueprint $table) {
            $cols = [
                'noc_ref', 'closed_at', 'installments_paid', 'next_due_date', 'interest_rate',
                'down_payment', 'emi_amount', 'product_name', 'product_id', 'account_no',
                'loan_application_id', 'customer_id',
            ];
            $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('loans', $c)));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $cols = ['emi_amount', 'down_payment', 'tenure_months', 'product_id', 'application_payload'];
            $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('loan_applications', $c)));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $cols = ['is_active', 'last_login_at', 'aadhaar_last4', 'monthly_income', 'dob', 'whatsapp_opt_in'];
            $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('customers', $c)));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
