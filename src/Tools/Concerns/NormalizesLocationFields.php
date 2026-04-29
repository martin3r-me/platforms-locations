<?php

namespace Platform\Locations\Tools\Concerns;

/**
 * Normalisiert Locations-Felder zwischen verschiedenen Eingabe-Konventionen
 * (z. B. anlaesse als Komma-/Semikolon-getrennten String statt Array).
 *
 * Arbeitet in-place auf $arguments und liefert die Liste der angewandten
 * Aliases zurueck (fuer aliases_applied im Response).
 *
 * Pattern analog zu Events-NormalizesTimeFields-Concern.
 */
trait NormalizesLocationFields
{
    /**
     * @param array<string, mixed> $arguments
     * @return array<int, string> Liste der angewandten Aliases (z.B. "anlaesse:string→array")
     */
    protected function normalizeLocationFields(array &$arguments): array
    {
        $applied = [];

        // anlaesse: tolerant — String-Liste (Komma/Semikolon getrennt) -> Array
        if (array_key_exists('anlaesse', $arguments) && is_string($arguments['anlaesse'])) {
            $raw = trim((string) $arguments['anlaesse']);
            if ($raw === '') {
                $arguments['anlaesse'] = null;
                $applied[] = 'anlaesse:empty-string→null';
            } else {
                // Erlaube Komma ODER Semikolon als Trenner
                $parts = preg_split('/[,;]/', $raw) ?: [];
                $cleaned = collect($parts)
                    ->map(fn ($s) => trim((string) $s))
                    ->filter(fn ($s) => $s !== '')
                    ->values()
                    ->all();
                $arguments['anlaesse'] = $cleaned !== [] ? $cleaned : null;
                $applied[] = 'anlaesse:string→array';
            }
        }

        // Englische Aliases auf deutsche Pflicht-Felder
        $aliasMap = [
            'address'     => 'adresse',
            'description' => 'beschreibung',
            'highlight'   => 'besonderheit',
            'occasions'   => 'anlaesse',
            'size_sqm'    => 'groesse_qm',
            'hall_number' => 'hallennummer',
            'accessible'  => 'barrierefrei',
        ];
        foreach ($aliasMap as $alias => $primary) {
            if (array_key_exists($alias, $arguments) && !array_key_exists($primary, $arguments)) {
                $arguments[$primary] = $arguments[$alias];
                $applied[] = "{$alias}→{$primary}";
            }
        }

        return $applied;
    }
}
