<?php
declare(strict_types=1);

/** مخطط أعمدة مجمّعة بصيغة SVG (بدون مكتبات خارجية). $series = [[name, color, values[]], ...] */
function svg_bar_chart(array $labels, array $series, string $ariaLabel = 'رسم بياني'): string {
    $n = count($labels);
    if ($n === 0) return '';
    $W = 760; $H = 280; $pl = 56; $pr = 12; $pt = 14; $pb = 40;
    $max = 0.0;
    foreach ($series as $s) foreach ($s[2] as $v) $max = max($max, (float)$v);
    if ($max <= 0) return '<div class="empty-inline">لا توجد بيانات كافية لرسم المخطط في هذه الفترة.</div>';
    // قيمة محور مقرّبة
    $pow = 10 ** max(0, strlen((string)(int)$max) - 1);
    $top = ceil($max / $pow) * $pow; if ($top <= 0) $top = $max;
    $cw = ($W - $pl - $pr) / $n; $sc = count($series); $bw = min(26, ($cw * 0.72) / $sc);
    $ch = $H - $pt - $pb;
    $o = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($ariaLabel) . '" direction="ltr" style="direction:ltr">';
    for ($g = 0; $g <= 4; $g++) {
        $y = $pt + $ch - ($ch * $g / 4); $val = $top * $g / 4;
        $o .= '<line x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . $y . '" y2="' . $y . '" stroke="#E4DACB" stroke-width="1"/>';
        $o .= '<text x="' . ($pl - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(number_format($val, 0)) . '</text>';
    }
    foreach ($labels as $i => $lab) {
        $cx = $pl + $cw * $i + $cw / 2;
        $startX = $cx - ($bw * $sc + 3 * ($sc - 1)) / 2;
        foreach ($series as $k => $s) {
            $v = (float)($s[2][$i] ?? 0); $h = $ch * $v / $top;
            $x = $startX + $k * ($bw + 3); $y = $pt + $ch - $h;
            $o .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max($h, $v > 0 ? 1.5 : 0), 1) . '" rx="4" fill="' . e($s[1]) . '"><title>' . e($s[0] . ' — ' . $lab . ': ' . number_format($v, 2)) . '</title></rect>';
        }
        $o .= '<text x="' . round($cx, 1) . '" y="' . ($H - 14) . '" text-anchor="middle">' . e($lab) . '</text>';
    }
    return $o . '</svg>';
}

function chart_legend(array $series): string {
    $h = '<div class="legend">';
    foreach ($series as $s) $h .= '<span><i style="background:' . e($s[1]) . '"></i>' . e($s[0]) . '</span>';
    return $h . '</div>';
}

/** أشهر متتالية Y-m بين تاريخين (بحد أقصى 36 شهراً) */
function months_between(string $from, string $to): array {
    $out = []; $d = substr($from, 0, 7) . '-01'; $end = substr($to, 0, 7) . '-01';
    for ($i = 0; $i < 36 && $d <= $end; $i++) { $out[] = substr($d, 0, 7); $d = add_months($d, 1); }
    return $out;
}
function short_month_label(string $ym): string { [$y, $m] = explode('-', $ym); return AR_MONTHS[(int)$m] . ' ' . substr($y, 2); }
