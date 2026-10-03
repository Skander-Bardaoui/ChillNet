<?php

namespace Tests\Unit;

use App\Enums\NiveauAlerte;
use PHPUnit\Framework\TestCase;

class NiveauAlerteTest extends TestCase
{
    public function test_labels_badges_colors_and_icons_are_defined(): void
    {
        $this->assertSame('Vigilance jaune', NiveauAlerte::Jaune->label());
        $this->assertSame('Vigilance orange', NiveauAlerte::Orange->label());
        $this->assertSame('Vigilance rouge', NiveauAlerte::Rouge->label());

        $this->assertSame('bg-amber-100 text-amber-800', NiveauAlerte::Jaune->badgeClasses());
        $this->assertSame('bg-orange-100 text-orange-800', NiveauAlerte::Orange->badgeClasses());
        $this->assertSame('bg-red-100 text-red-800', NiveauAlerte::Rouge->badgeClasses());

        $this->assertSame('#f59e0b', NiveauAlerte::Jaune->couleurHex());
        $this->assertSame('#ea580c', NiveauAlerte::Orange->couleurHex());
        $this->assertSame('#dc2626', NiveauAlerte::Rouge->couleurHex());

        $this->assertSame('thermostat', NiveauAlerte::Jaune->icone());
        $this->assertSame('wb_sunny', NiveauAlerte::Orange->icone());
        $this->assertSame('warning', NiveauAlerte::Rouge->icone());
    }

    public function test_severity_increases_from_yellow_to_red(): void
    {
        $this->assertSame(1, NiveauAlerte::Jaune->gravite());
        $this->assertSame(2, NiveauAlerte::Orange->gravite());
        $this->assertSame(3, NiveauAlerte::Rouge->gravite());
    }

    public function test_plus_grave_returns_the_most_severe_level(): void
    {
        $this->assertSame(
            NiveauAlerte::Rouge,
            NiveauAlerte::plusGrave([NiveauAlerte::Jaune, NiveauAlerte::Rouge, NiveauAlerte::Orange]),
        );

        $this->assertSame(NiveauAlerte::Orange, NiveauAlerte::plusGrave([NiveauAlerte::Jaune, NiveauAlerte::Orange]));
        $this->assertNull(NiveauAlerte::plusGrave([]));
    }
}
