<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function coordinatorIndex()
    {
        // proposals that have been endorsed by secretary and awaiting coordinator review
        $proposals = Proposal::where('status', 'endorsed_by_secretary')->with('applicant')->get();
        return view('CELP.coordinator.proposals.index', ['proposals' => $proposals]);
    }

    public function show(Proposal $proposal)
    {
        return view('proposals.show', ['proposal' => $proposal]);
    }

    public function coordinatorReview(Request $request, Proposal $proposal)
    {
        $request->validate([
            'action' => 'required|in:endorse,return',
            'comments' => 'nullable|string',
        ]);

        if ($request->action === 'endorse') {
            $proposal->status = 'endorsed_by_coordinator';
            $proposal->coordinator_comments = $request->comments;
            $proposal->save();
        } else {
            $proposal->status = 'returned_by_coordinator';
            $proposal->coordinator_comments = $request->comments;
            $proposal->save();
        }

        return redirect()->route('proposals.second_review')->with('status', 'Action saved.');
    }
}
