<?php

namespace Platform\Locations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Locations\Models\Location;
use Platform\Locations\Services\BookletPdfService;
use Symfony\Component\HttpFoundation\Response;

class BookletController extends Controller
{
    public function __construct(private BookletPdfService $pdf)
    {
    }

    /**
     * Auth-Download fuer den User aus dem Manage/Show-View. Greift direkt
     * via UUID, prueft Team-Access.
     */
    public function authDownload(Location $location): Response
    {
        $team = Auth::user()?->currentTeam;
        if (!$team || (int) $location->team_id !== (int) $team->id) {
            abort(404);
        }

        return $this->pdf->downloadResponse($location);
    }

    /**
     * Public HTML-View des Booklets fuer den Kunden. Token-basiert,
     * Auth-frei. 410 Gone wenn Token abgelaufen.
     */
    public function publicShow(string $token)
    {
        $location = $this->resolveByToken($token);

        return response($this->pdf->renderHtml($location), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Public PDF-Stream des Booklets fuer den Kunden. Inline (Browser-PDF-Viewer).
     */
    public function publicPdf(string $token): Response
    {
        $location = $this->resolveByToken($token);

        return $this->pdf->inlineResponse($location)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Token-Lookup mit Expiry-Check. Werft 410 (Gone) wenn Token abgelaufen,
     * 404 wenn unbekannt. Beide Pfade rendern keine sensiblen Daten — die
     * Default-Error-Pages der Host-App genuegen.
     */
    protected function resolveByToken(string $token): Location
    {
        if (strlen($token) < 20 || strlen($token) > 64) {
            abort(404);
        }

        $location = Location::query()
            ->where('booklet_share_token', $token)
            ->first();

        if (!$location) {
            abort(404);
        }

        if ($location->booklet_share_expires_at !== null && $location->booklet_share_expires_at->isPast()) {
            abort(410, 'Dieser Link ist abgelaufen.');
        }

        return $location;
    }
}
