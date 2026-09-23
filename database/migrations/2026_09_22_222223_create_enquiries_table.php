<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            // The name as it was when the customer asked, so the record still
            // says which car they wanted after the car is renamed or removed.
            $table->string('vehicle_name')->nullable();
            $table->string('location')->nullable();
            // App\Voltiva\DrivingNeed values the customer ticked.
            $table->json('driving_needs')->nullable();
            $table->boolean('finance_interest')->default(false);
            $table->boolean('registration_interest')->default(false);
            $table->text('message')->nullable();

            // Where the form was opened from (vehicle page, finder, compare...).
            $table->string('source', 50);
            $table->string('locale', 5)->default('en');

            // App\Voltiva\EnquiryStatus: the sales team's follow-up status.
            $table->string('status', 30)->default('new');
            $table->text('staff_notes')->nullable();

            // The automatic email sequence: how many of its emails have gone
            // out, and when the next one is due. Null means nothing is due --
            // the sequence finished or the enquiry was closed.
            $table->unsignedTinyInteger('follow_up_step')->default(0);
            $table->timestamp('next_follow_up_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('next_follow_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
