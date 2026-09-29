<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 12 Aug 2026 — per Chris: "you should have the standard naming/folder
// for each vendor. main folder vendor name sub folder individual
// category, SSM Sub folder, video, flyers, urlink slide show, bankruptcy
// pdf, etc. later when vendor submit a rebate promotion program
// marketing/product flyers/ppt etc all these upload is also store in the
// same manner." ONE shared place every vendor file upload goes through —
// SSM documents, marketing media, bankruptcy reports, and (once built)
// rebate/promotion program marketing materials — so every category lands
// under the same vendor's folder with the same naming rule, instead of
// each feature inventing its own folder layout.
//
// Layout: storage/app/private/vendors/{vendor-name-slug}-{short id}/{category}/{real filename}.ext
// The short vendor_id suffix is appended only to guarantee two vendors
// with the same/similar name never collide, and so the folder still
// belongs to the same vendor even if they later rename their company —
// the folder itself stays human-readable, browsable by name.
class VendorFileStorageService
{
    public const CATEGORY_SSM = 'ssm-documents';
    public const CATEGORY_PROFILE = 'profile';
    public const CATEGORY_VIDEO = 'video';
    public const CATEGORY_FLYERS = 'flyers';
    public const CATEGORY_SLIDESHOW = 'slideshow';
    public const CATEGORY_BANKRUPTCY = 'bankruptcy';
    public const CATEGORY_CTOS = 'ctos-report';
    // RESERVED 12 Aug 2026 — not built yet. When the vendor rebate/
    // promotion program marketing upload feature is built, its files
    // must be stored via this same service under this category so they
    // land in the same per-vendor folder as everything else.
    public const CATEGORY_REBATE_MARKETING = 'rebate-marketing';
    // NEW 13 Aug 2026 — attachments sent through the Vendor Onboarding
    // and Communication Workflow thread (either side — admin or vendor).
    public const CATEGORY_QA_ATTACHMENT = 'qa-attachments';

    public static function vendorFolder(string $vendorId, string $vendorName, string $category): string
    {
        $slug = Str::slug($vendorName) ?: 'vendor';
        $shortId = substr($vendorId, 0, 8);
        return "vendors/{$slug}-{$shortId}/{$category}";
    }

    /** Stores the file under the vendor's own folder/category, keeping the real filename, auto-versioning (_v2, _v3...) instead of overwriting on a repeat upload. Returns the stored relative path (save this into file_path). */
    public static function store(UploadedFile $file, string $vendorId, string $vendorName, string $category, string $disk = 'local'): string
    {
        $dir = self::vendorFolder($vendorId, $vendorName, $category);
        $ext = $file->getClientOriginalExtension();
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: $category;

        $name = $base . '.' . $ext;
        $version = 1;
        while (Storage::disk($disk)->exists($dir . '/' . $name)) {
            $version++;
            $name = $base . '_v' . $version . '.' . $ext;
        }

        return $file->storeAs($dir, $name, $disk);
    }
}
