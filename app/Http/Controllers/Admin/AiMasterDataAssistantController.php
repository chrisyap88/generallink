<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;

// NEW 19 Sep 2026 -- per Chris's uploaded spec
// "AI_Master_Data_and_Transaction_Assistant_Specification.docx": one AI
// Assistant that lets a non-accountant describe a master-data need in
// plain language instead of learning which Master File to open. Chris
// confirmed the build order: Phase 1 is Chart of Accounts only ("COA
// Chat" -- already wired to CbeAccountingController::coaChatForm()),
// with Supplier, Customer, Bank Account, Fixed Asset, Cost Centre and
// Tax Code added one at a time after that. Part 2 of the spec (the AI
// also auto-creating transactions like an Expense Claim) is
// deliberately set aside for now -- GeneralLink has no Expense Claim
// module yet for it to bridge into.
//
// This hub is just a menu of entity types: whichever are wired show a
// live link, everything else shows as "Coming Soon" so Chris can see
// the full roadmap without any of it being clickable before it's real.
class AiMasterDataAssistantController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        $entities = [
            ['key' => 'coa', 'ready' => true],
            ['key' => 'supplier', 'ready' => false],
            ['key' => 'customer', 'ready' => false],
            ['key' => 'bank_account', 'ready' => false],
            ['key' => 'fixed_asset', 'ready' => false],
            ['key' => 'cost_centre', 'ready' => false],
            ['key' => 'tax_code', 'ready' => false],
        ];

        return view('masterfile.ai-assistant', compact('entities', 'nodeId'));
    }
}
