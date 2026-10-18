<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicJdihController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE J — JDIH outbound link (`/jdih`; owner requirements + D-J1…D-J5).
 *
 * Acceptance criteria under test:
 *   · the notice page is public (no login) and states the destination;
 *   · it NEVER auto-redirects — no meta refresh, no scripted location change;
 *     the exit happens only from the visitor's explicit action;
 *   · the single outbound action is the official address, in a new tab, with
 *     rel="noopener noreferrer";
 *   · the landing links to the notice page and never straight to the site.
 *
 * See JDIH_INTEGRATION_PLAN.md.
 */
class JdihNoticePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notice_page_is_publicly_readable_without_login(): void
    {
        $this->get(route('jdih.index'))
            ->assertOk()
            ->assertSee('Jaringan Dokumentasi dan Informasi Hukum', false)
            ->assertSee('jdih.pareparekota.go.id', false);
    }

    public function test_the_notice_page_states_the_destination_before_leaving(): void
    {
        $this->get(route('jdih.index'))
            ->assertOk()
            ->assertSee('meninggalkan SuperBie', false)
            ->assertSee(PublicJdihController::DESTINATION, false);
    }

    public function test_the_notice_page_never_auto_redirects(): void
    {
        $html = $this->get(route('jdih.index'))->assertOk()->getContent();

        // The only way out is the visitor's explicit click: no meta refresh and
        // no scripted navigation anywhere on the page.
        $this->assertStringNotContainsString('http-equiv="refresh"', $html);
        $this->assertStringNotContainsString('window.location', $html);
        $this->assertStringNotContainsString('location.replace', $html);
        $this->assertStringNotContainsString('location.href', $html);
    }

    public function test_the_outbound_action_is_the_official_address_in_a_new_tab(): void
    {
        $html = $this->get(route('jdih.index'))->assertOk()->getContent();

        // Exactly one outbound anchor, pointing at the official address.
        $this->assertSame(
            1,
            substr_count($html, 'href="'.PublicJdihController::DESTINATION.'"'),
            'the destination must appear as exactly one explicit outbound link'
        );

        $anchor = null;
        if (preg_match('/<a[^>]*href="https:\/\/jdih\.pareparekota\.go\.id\/"[^>]*>/', $html, $m)) {
            $anchor = $m[0];
        }

        $this->assertNotNull($anchor, 'the outbound anchor must be present');
        $this->assertStringContainsString('target="_blank"', $anchor);
        $this->assertStringContainsString('rel="noopener noreferrer"', $anchor);
    }

    public function test_the_landing_links_to_the_notice_page_never_the_external_site(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Two doors on the landing: the footer door + the "Layanan" list.
        $this->assertSame(2, substr_count($html, 'href="'.route('jdih.index').'"'));

        // The portal never sends a visitor straight to the external site.
        $this->assertStringNotContainsString(PublicJdihController::DESTINATION, $html);
    }
}
