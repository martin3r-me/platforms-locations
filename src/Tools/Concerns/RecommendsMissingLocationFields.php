<?php

namespace Platform\Locations\Tools\Concerns;

use Platform\Locations\Models\Location;

/**
 * Liefert Diagnose-Hilfsmaps für Locations-Tools:
 *  - emptyRecommendedFields(): Map aus noch leeren, fachlich wichtigen Feldern + Erklaerung
 *  - recommendedFieldOptions(): erlaubte Werte (Enum/Hint) pro Pickliste-Feld
 *
 * Pattern analog Events-`RecommendsMissingFields`. Bewusst keine harten
 * Pflichtfelder — nur Hinweise, kein Block.
 */
trait RecommendsMissingLocationFields
{
    /**
     * @return array<string, string>
     */
    protected function emptyRecommendedLocationFields(Location $location): array
    {
        $location->loadMissing(['pricings', 'addons', 'seatingOptions']);
        $missing = [];

        if (empty($location->pax_max)) {
            $missing['pax_max'] = 'Maximale Kapazitaet (inkl. Personal). Wichtig fuer Buchungs-Validierung im Events-Modul.';
        }
        if (empty($location->adresse) || $location->latitude === null || $location->longitude === null) {
            $missing['adresse'] = 'Adresse + Koordinaten (Lat/Lng). Bei Eingabe via Tool (POST/PATCH) wird die Adresse automatisch via Nominatim geocoded — siehe geocoding-Feld im Response. In der Manage-UI wird sie beim Auswaehlen eines Vorschlags gesetzt.';
        }
        if (empty($location->groesse_qm)) {
            $missing['groesse_qm'] = 'Groesse in qm. Hilft bei Filter-Suche und Angebotstexten.';
        }
        if (empty($location->besonderheit)) {
            $missing['besonderheit'] = 'Kurze Hervorhebung (1-2 Saetze, z. B. "3 verfahrbare Kronleuchter"). Erscheint in Listen und Karten.';
        }
        if (empty($location->beschreibung)) {
            $missing['beschreibung'] = 'Langer Beschreibungs-/Storytext fuer Marketing, Historie, Kundeninfo. Wird spaeter z. B. in Quote-PDFs verwendet.';
        }
        if (empty($location->anlaesse)) {
            $missing['anlaesse'] = 'Liste geeigneter Anlaesse als Tag-Liste (z. B. ["Hochzeit", "Firmenfeier", "Tagung"]). Filter-Hilfe.';
        }
        if ($location->seatingOptions->isEmpty()) {
            $missing['seating_options'] = 'Keine Bestuhlungs-Hinweise gepflegt. Anlegen via locations.seating-options.POST (z. B. Reihenbestuhlung 270, Runde 10er 220).';
        }
        if ($location->pricings->isEmpty()) {
            $missing['pricings'] = 'Keine Mietpreise gepflegt. Anlegen via locations.pricings.POST mit day_type_label + price_net. Ohne Pricing kann das Events-Modul keine Mietposten einbuchen.';
        }
        if ($location->addons->isEmpty()) {
            $missing['addons'] = 'Keine Add-ons (z. B. Heizung) gepflegt. Optional, aber typisch bei externen Locations. Anlegen via locations.addons.POST.';
        }

        return $missing;
    }

    /**
     * @return array<string, array{values: array<int,string>, strict: bool, note: string}>
     */
    protected function recommendedLocationFieldOptions(?int $teamId = null): array
    {
        $opts = [];

        // anlaesse — Soft-Hints (frei erweiterbar)
        $opts['anlaesse'] = [
            'values' => ['Hochzeit', 'Firmenfeier', 'Tagung', 'Empfang', 'Gala', 'Konzert', 'Messe', 'Workshop'],
            'strict' => false,
            'note'   => 'Vorschlaege. Freitext-Werte werden akzeptiert. Liste ist nicht zentral gepflegt.',
        ];

        return $opts;
    }

    /**
     * Liefert Hints fuer Sub-Entity-Felder, deren Werte gegen Pickliste/Enum
     * laufen — fuer locations.pricings.* / locations.addons.*.
     *
     * @return array<string, array{values: array<int,string>, strict: bool, note: string}>
     */
    protected function recommendedSubEntityFieldOptions(?int $teamId = null): array
    {
        $opts = [];

        // day_type_label (Pricings) — Volltext gegen events_settings.day_types
        $dayTypes = ['Veranstaltungstag', 'Aufbautag', 'Abbautag', 'Ruesttag'];
        if ($teamId !== null && class_exists('\\Platform\\Events\\Services\\SettingsService')) {
            try {
                $resolved = \Platform\Events\Services\SettingsService::dayTypes($teamId);
                if (is_array($resolved) && !empty($resolved)) {
                    $dayTypes = $resolved;
                }
            } catch (\Throwable $e) {
                // ignore — fallback bleibt
            }
        }
        $opts['day_type_label'] = [
            'values' => $dayTypes,
            'strict' => false,
            'note'   => 'Volltext-Match gegen events_settings.day_types des aktuellen Teams. Freitext-Werte werden akzeptiert, sind aber nicht praktisch nutzbar (kein Tag-Typ-Match beim Einbuchen).',
        ];

        // unit (Addons) — strikt
        $opts['unit'] = [
            'values' => ['pro_tag', 'pro_va_tag', 'einmalig', 'pro_stueck'],
            'strict' => true,
            'note'   => 'Hardcoded Enum. Andere Werte werden mit VALIDATION_ERROR abgelehnt. Steuert die Default-Menge beim Einbuchen.',
        ];

        return $opts;
    }
}
