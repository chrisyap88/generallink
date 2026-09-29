<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 17 Sep 2026 — per Chris's "Google Doodle" idea: a notice's own
// wording decides how it looks (Security Alert red, Celebration gold
// dots, Birthday blue, Promo orange stripes, etc). Automatic by
// default; Admin can turn a style off, edit its colours/keywords, or
// add new ones — same admin-editable-catalog pattern as Faith Types.
class CbeNoticeStyleService
{
    /** All active styles, ordered — cached for the life of the request. */
    public function activeStyles()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = DB::table('cbe_notice_styles')->where('is_active', true)->orderBy('sort_order')->get();
        }
        return $cache;
    }

    /** One style row by key, or the GENERAL fallback if missing/inactive. */
    public function find(?string $styleKey)
    {
        if ($styleKey) {
            $row = $this->activeStyles()->firstWhere('style_key', $styleKey);
            if ($row) {
                return $row;
            }
        }
        return $this->activeStyles()->firstWhere('style_key', 'GENERAL')
            ?? (object) ['style_key' => 'GENERAL', 'label' => 'General', 'icon_key' => 'bell', 'bg_color' => '#FFFFFF', 'accent_color' => '#546E7A', 'text_color' => '#263238'];
    }

    /**
     * Scan title+body for each active style's keywords (in sort order,
     * so Admin can control which wins when two types both match) and
     * return the first match's style_key, or GENERAL if none match.
     */
    public function detect(string $title, string $body): string
    {
        $haystack = mb_strtolower($title.' '.$body);

        foreach ($this->activeStyles() as $style) {
            if ($style->style_key === 'GENERAL' || ! $style->keywords) {
                continue;
            }
            foreach (explode(',', $style->keywords) as $kw) {
                $kw = trim(mb_strtolower($kw));
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    return $style->style_key;
                }
            }
        }

        return 'GENERAL';
    }
}
