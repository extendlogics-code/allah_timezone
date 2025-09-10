<?php
declare(strict_types=1);

namespace App;

final class Times
{
    public static function norm_header(string $s): string { return strtolower(trim(preg_replace('/\xEF\xBB\xBF/', '', $s))); }
    public static function hhmm_ok(string $t): bool { return (bool)preg_match('~^(?:[01]\d|2[0-3]):[0-5]\d$~', trim($t)); }

    /**
     * Load and merge CSV files into selection data + schedule for a given date and selection.
     * Returns array with: countryList, stateList, cityList, hasAnyCity, selection, scheduleTimes, scheduleNames, chosenDate.
     */
    public static function load(array $csvFiles, string $date, string $country = '', string $state = '', string $city = ''): array
    {
        $hasAnyCity = false;
        $countryList = [];
        $stateListByCountry = [];
        $cityListByCountryState = [];
        $rowsByCountryState = [];
        $rowsByCountryStateCity = [];

        $required = ['country','state','fajr','dhuhr','asr','maghrib','isha','date'];
        foreach ($csvFiles as $csvPath) {
            if (!is_readable($csvPath)) continue;
            if (($fh=fopen($csvPath,'r'))===false) continue;

            $header = fgetcsv($fh, 0, ',', '"', '\\');
            if ($header === false) { fclose($fh); continue; }
            $header = array_map([self::class,'norm_header'], $header);

            $ok = true; foreach ($required as $col) { if (!in_array($col, $header, true)) { $ok=false; break; } }
            if (!$ok){ fclose($fh); continue; }

            $idx = array_flip($header);
            $fileHasCity = in_array('city', $header, true);
            if ($fileHasCity) $hasAnyCity = true;

            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false){
                if (count($row) < count($header)) continue;
                $ctry = trim($row[$idx['country']] ?? '');
                $st   = trim($row[$idx['state']]   ?? '');
                $dt   = trim($row[$idx['date']]    ?? '');
                if ($ctry==='' || $st==='' || $dt==='') continue;

                $rec = [
                    'country' => $ctry,
                    'state'   => $st,
                    'city'    => $fileHasCity ? trim($row[$idx['city']] ?? '') : '',
                    'fajr'    => trim($row[$idx['fajr']]    ?? ''),
                    'dhuhr'   => trim($row[$idx['dhuhr']]   ?? ''),
                    'asr'     => trim($row[$idx['asr']]     ?? ''),
                    'maghrib' => trim($row[$idx['maghrib']] ?? ''),
                    'isha'    => trim($row[$idx['isha']]    ?? ''),
                    'date'    => $dt,
                ];

                $countryList[$ctry] = true;
                $stateListByCountry[$ctry][$st] = true;
                $rowsByCountryState[$ctry][$st][] = $rec;

                if ($fileHasCity && $rec['city']!==''){
                    $city = $rec['city'];
                    $cityListByCountryState[$ctry][$st][$city] = true;
                    $rowsByCountryStateCity[$ctry][$st][$city][] = $rec;
                }
            }
            fclose($fh);
        }

        // Sort lists
        $countryList = array_keys($countryList);
        sort($countryList, SORT_NATURAL|SORT_FLAG_CASE);
        if ($country==='' && !empty($countryList)) $country = $countryList[0];

        $stateList = [];
        if ($country!=='' && !empty($stateListByCountry[$country])) {
            $stateList = array_keys($stateListByCountry[$country]);
            sort($stateList, SORT_NATURAL|SORT_FLAG_CASE);
            if ($state==='' && !empty($stateList)) $state = $stateList[0];
        }

        $cityList = [];
        if ($hasAnyCity && $country!=='' && $state!=='' && !empty($cityListByCountryState[$country][$state])) {
            $cityList = array_keys($cityListByCountryState[$country][$state]);
            sort($cityList, SORT_NATURAL|SORT_FLAG_CASE);
            if ($city==='' && !empty($cityList)) $city = $cityList[0];
        }

        // Choose record
        $pick_row = function(array $rows, string $targetDate) : ?array {
            if (empty($rows)) return null;
            usort($rows, fn($a,$b)=>strcmp($a['date'],$b['date']));
            foreach ($rows as $r){ if (($r['date']??'') === $targetDate) return $r; }
            foreach ($rows as $r){ if (($r['date']??'') > $targetDate)  return $r; }
            return end($rows) ?: null;
        };
        $chosen = null;
        if ($country!=='' && $state!=='') {
            if ($hasAnyCity && $city!=='' && !empty($rowsByCountryStateCity[$country][$state][$city])) {
                $chosen = $pick_row($rowsByCountryStateCity[$country][$state][$city], $date);
            } elseif (!empty($rowsByCountryState[$country][$state])) {
                $chosen = $pick_row($rowsByCountryState[$country][$state], $date);
            }
        }

        $scheduleTimes = [];
        $scheduleNames = [];
        if ($chosen){
            $pairs = [
                ['Fajr',    $chosen['fajr'] ?? ''],
                ['Dhuhr',   $chosen['dhuhr'] ?? ''],
                ['Asr',     $chosen['asr'] ?? ''],
                ['Maghrib', $chosen['maghrib'] ?? ''],
                ['Isha',    $chosen['isha'] ?? ''],
            ];
            foreach($pairs as [$name,$t]){
                if (self::hhmm_ok($t)){ $scheduleNames[]=$name; $scheduleTimes[]=$t; }
            }
            array_multisort($scheduleTimes, SORT_ASC, $scheduleNames);
        }

        return [
            'countryList' => $countryList,
            'stateList'   => $stateList,
            'cityList'    => $cityList,
            'hasAnyCity'  => $hasAnyCity,
            'selection'   => [ 'country'=>$country, 'state'=>$state, 'city'=>$city ],
            'scheduleTimes' => $scheduleTimes,
            'scheduleNames' => $scheduleNames,
            'chosenDate'    => $chosen['date'] ?? null,
        ];
    }
}

