<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Validator;
class JobService{

    public function __construct(){

    }

    public function submitWork(Request $request)
    {
        // return $request;
        $this->validate($request, [
            'proof' => 'sometimes|image|mimes:png,jpeg,gif,jpg',
            'comment' => 'required|string',
            'campaign_id' => 'required|string'
            // 'amount' => 'required',
        ]);

        try {
            $check = CampaignWorker::where('user_id', auth()->user()->id)->where('campaign_id', $request->campaign_id)->first();
            // if($check){
            //     return response()->json(['status' => false, 'message' => 'You have comppleted this campaign before'], 401);
            // }

            $campaign = Campaign::where('id', $request->campaign_id)->first();
            // if(auth()->user()->id == $campaign->user_id){
            //     return response()->json(['status' => false, 'message' => 'You cannot do this job, you created it'], 401);
            // }

            $data['campaign'] = $campaign;

            // if($request->hasFile('proof')){
            $proofUrl = '';
            if ($request->hasFile('proof')) {
                $fileBanner = $request->file('proof');
                $Bannername = time() . $fileBanner->getClientOriginalName();
                $filePathBanner = 'proofs/' . $Bannername;

                Storage::disk('s3')->put($filePathBanner, file_get_contents($fileBanner), 'public');
                $proofUrl = Storage::disk('s3')->url($filePathBanner);
            }

            $campaignWorker['user_id'] = auth()->user()->id;
            $campaignWorker['campaign_id'] = $request->campaign_id;
            $campaignWorker['comment'] = $request->comment;
            $campaignWorker['amount'] = $campaign->campaign_amount;
            $campaignWorker['proof_url'] = $proofUrl == '' ? 'no image' : $proofUrl;
            $campaignWorker['currency'] = $campaign->currency;
            $campaignWork = CampaignWorker::create($campaignWorker);

            //activity log
            $campaign->pending_count += 1;
            $campaign->save();

            setPendingCount($campaign->id);

            $name = SystemActivities::getInitials(auth()->user()->name);
            SystemActivities::activityLog(auth()->user(), 'campaign_submission', $name . ' submitted a campaign of NGN' . number_format($request->amount), 'regular');

            Mail::to(auth()->user()->email)->send(new SubmitJob($campaignWork)); //send email to the member

            $campaign = Campaign::where('id', $request->campaign_id)->first();
            $user = User::where('id', $campaign->user->id)->first();
            $subject = 'Job Submission';
            $content = auth()->user()->name . ' submitted a response to the your campaign - ' . $campaign->post_title . '. Please login to review.';
            Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Work Submitted Successfully', 'data' => $campaignWork], 201);
    }

    public function mySubmittedCampaign($id)
    {
        $work = CampaignWorker::where('id', $id)->first();
        if (!$work) {
            return redirect('home');
        }
        return view('user.campaign.my_submitted_campaign', ['work' => $work]);
    }
}
