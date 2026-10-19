<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconnaissance faciale (Face++, connexion par scan du visage).
     *  - face_reference_image   : photo de référence ré-encodée (chiffrée par le cast Eloquent),
     *  - face_enrolled_at       : date d'activation de la connexion par visage,
     *  - face_consent_at        : date du consentement explicite (donnée biométrique),
     *  - face_consent_version   : version du texte de consentement accepté.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->mediumText('face_reference_image')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('face_enrolled_at')->nullable()->after('face_reference_image');
            $table->timestamp('face_consent_at')->nullable()->after('face_enrolled_at');
            $table->string('face_consent_version', 20)->nullable()->after('face_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'face_reference_image',
                'face_enrolled_at',
                'face_consent_at',
                'face_consent_version',
            ]);
        });
    }
};
