<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('publication_event_states');
    }

    public function down(): void
    {
        // Publication event state was an obsolete runtime projection and is not recreated.
    }
};
