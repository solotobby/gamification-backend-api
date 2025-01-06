<?php

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

    public function approveCampaign($id)
    {
        $approve = CampaignWorker::where('id', $id)->first();
        if ($approve->reason != null) {
            return back()->with('error', 'Campaign has been attended to');
        }
        $approve->status = 'Approved';
        $approve->reason = 'Approved by User';
        $approve->save();

        $currency = '';
        $channel = '';
        if ($approve->currency == 'NGN') {
            $currency = 'NGN';
            $channel = 'paystack';
            $wallet = Wallet::where('user_id', $approve->user_id)->first();
            $wallet->balance += $approve->amount;
            $wallet->save();
        } else {
            $currency = 'NGN';
            $channel = 'paystack';
            $wallet = Wallet::where('user_id', $approve->user_id)->first();
            $wallet->usd_balance += $approve->amount;
            $wallet->save();
        }

        $ref = time();
        PaymentTransaction::create([
            'user_id' => $approve->user_id,
            'campaign_id' => $approve->campaign->id,
            'reference' => $ref,
            'amount' => $approve->amount,
            'status' => 'successful',
            'currency' => $currency,
            'channel' => $channel,
            'type' => 'campaign_payment',
            'description' => 'Campaign Payment for ' . $approve->campaign->post_title,
            'tx_type' => 'Credit',
            'user_type' => 'regular'
        ]);

        $subject = 'Job Approved';
        $status = 'Approved';
        Mail::to($approve->user->email)->send(new ApproveCampaign($approve, $subject, $status));

        return back()->with('success', 'Campaign Approve Successfully');
    }

    public function denyCampaign($id)
    {
        $deny = CampaignWorker::where('id', $id)->first();
        $deny->status = 'Denied';
        $deny->reason = 'Denied by User';
        $deny->save();
        $subject = 'Job Denied';
        $status = 'Denied';
        Mail::to($deny->user->email)->send(new ApproveCampaign($deny, $subject, $status));
        return back()->with('error', 'Campaign Denied Successfully');
    }

    public function approvedCampaigns()
    {
        try {
            $mycampaigns = Campaign::where('user_id', auth()->user()->id)->pluck('id')->toArray();
            $approved = CampaignWorker::with(['user:id,name', 'campaign:id,post_title'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Approved')->orderby('created_at', 'ASC')->select(['id', 'user_id', 'campaign_id', 'amount', 'status', 'created_at'])->paginate(10);
            if (!$approved) {
                return response()->json(['status' => false, 'message' => 'There are no Approved Campaigns'], 401);
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Approved Campaigns', 'data' =>  $approved], 200);
    }
    public function deniedCampaigns()
    {
        try {
            $mycampaigns = Campaign::where('user_id', auth()->user()->id)->pluck('id')->toArray();
            $denied = CampaignWorker::with(['user:id,name', 'campaign:id,post_title'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Denied')->orderby('created_at', 'ASC')->select(['id', 'user_id', 'campaign_id', 'amount', 'status', 'created_at'])->paginate(10); //CampaignWorker::with(['user:id,name'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Denied')->orderby('created_at', 'ASC')->paginate(10);
            if (!$denied) {
                return response()->json(['status' => false, 'message' => 'There are no Denied Campaigns'], 401);
            }
            // return view('user.campaign.denied', ['lists' => $denied]);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Denied Campaigns', 'data' => $denied], 200);
    }

    public function completedJobs()
    {
        $completedJobs = CampaignWorker::where('user_id', auth()->user()->id)->orderBy('created_at', 'ASC')->get();
        return view('user.campaign.completed_jobs', ['lists' => $completedJobs]);
    }

    public function disputedJobs()
    {
        $disputedJobs = CampaignWorker::where('user_id', auth()->user()->id)->where('is_dispute', true)->orderBy('created_at', 'ASC')->get();
        return view('user.campaign.disputed_jobs', ['lists' => $disputedJobs]);
    }

    public function processDisputedJobs($request)
    {
        $workDone = CampaignWorker::where('id', $request->id)->first();
        $workDone->is_dispute = true;
        $workDone->save();

        $disputedJob = DisputedJobs::create([
            'campaign_worker_id' => $workDone->id,
            'campaign_id' => $workDone->campaign_id,
            'user_id' => auth()->user()->id,
            'reason' => $request->reason
        ]);


        if ($disputedJob) {
            $subject = 'New Dispute Raised';
            $content = 'A despute has been raised by ' . auth()->user()->name . ' on a Job. Please follow the link below to attend to it.';
            $url = 'admin/campaign/disputes/' . $workDone->id;
            Mail::to('freebyzcom@gmail.com')->send(new GeneralMail(auth()->user(), $content, $subject, $url));
            return back()->with('success', 'Dispute Submitted Successfully');
        }
    }

    public function campaignDecision($request)
    {
        $request->validate([
            'reason' => 'required|string',
            'action' => 'required|string',
            'campaign_worker_id' => 'required|string',
        ]);

        try {

            $workSubmitted = CampaignWorker::where('id', $request->campaign_worker_id)->first();
            $campaign = Campaign::where('id', $workSubmitted->campaign_id)->first();

            if ($workSubmitted->reason != null) {
                return response()->json(['status' => false, 'message' => 'Campaign has been attended to'], 401);
            }
            if ($campaign->is_completed == true) {
                return response()->json(['status' => false, 'message' => 'Campaign has reached its maximum capacity'], 401);
            }


            if ($request->action == 'approve') {




                //    $completed_campaign = $campaign->completed()->where('status', 'Approved')->count();
                //    if($completed_campaign >= $campaign->number_of_staff){
                //         return back()->with('error', 'Campaign has reached its maximum capacity');
                //    }

                $user = User::where('id', $workSubmitted->user_id)->first();

                $workSubmitted->status = 'Approved';
                $workSubmitted->reason = $request->reason;
                $workSubmitted->save();

                //update completed action
                $campaign->completed_count += 1;
                $campaign->pending_count -= 1;
                $campaign->save();

                setIsComplete($workSubmitted->campaign_id);

                if ($campaign->currency == 'NGN') {
                    $currency = 'NGN';
                    $channel = 'paystack';
                    creditWallet($user, 'Naira', $workSubmitted->amount);
                } elseif ($campaign->currency == 'USD') {
                    $currency = 'USD';
                    $channel = 'paypal';
                    creditWallet($user, 'Dollar', $workSubmitted->amount);
                } elseif ($campaign->currency == null) {
                    $currency = 'NGN';
                    $channel = 'paystack';
                    creditWallet($user, 'Naira', $workSubmitted->amount);
                }


                $ref = time();

                PaymentTransaction::create([
                    'user_id' =>  $workSubmitted->user_id,
                    'campaign_id' =>  $workSubmitted->campaign->id,
                    'reference' => $ref,
                    'amount' =>  $workSubmitted->amount,
                    'status' => 'successful',
                    'currency' => $currency,
                    'channel' => $channel,
                    'type' => 'campaign_payment',
                    'description' => 'Campaign Payment for ' . $workSubmitted->campaign->post_title,
                    'tx_type' => 'Credit',
                    'user_type' => 'regular'
                ]);

                SystemActivities::activityLog($user, 'campaign_payment', $user->name . ' earned a campaign payment of NGN' . number_format($workSubmitted->amount), 'regular');

                $subject = 'Job Approved';
                $status = 'Approved';
                //    Mail::to($workSubmitted->user->email)->send(new ApproveCampaign($workSubmitted, $subject, $status));
                $data['decision_status'] = 'Campaign Approved';
                $data['work'] = $workSubmitted;
                //return back()->with('success', 'Campaign Approve Successfully');


            } else {

                //check if the
                // $chckCount = PaymentTransaction::where('user_id', $workSubmitted->campaign->user_id)->where('type', 'campaign_payment_refund')->whereDate('created_at', Carbon::today())->count();
                // if($chckCount >= 3){
                //     return back()->with('error', 'You cannot deny more than 3 jobs in a day');
                // }
                $workSubmitted->status = 'Denied';
                $workSubmitted->reason = $request->reason;
                $workSubmitted->save();

                $this->removePendingCountAfterDenial($workSubmitted->campaign_id);

                // $campaign = Campaign::where('id', $deny->campaign_id)->first();
                // $campaingOwner = User::where('id', $campaign->user_id)->first();

                if ($campaign->currency == 'NGN') {
                    $currency = 'Naira';
                    $channel = 'paystack';
                } elseif ($campaign->currency == 'USD') {
                    $currency = 'Dollar';
                    $channel = 'paypal';
                } elseif ($campaign->currency == null) {
                    $currency = 'Naira';
                    $channel = 'paystack';
                }

                // creditWallet($campaingOwner, $currency, $workSubmitted->amount);

                // $ref = time();

                // PaymentTransaction::create([
                //     'user_id' => $workSubmitted->campaign->user_id,
                //     'campaign_id' => $workSubmitted->campaign->id,
                //     'reference' => $ref,
                //     'amount' => $workSubmitted->amount,
                //     'status' => 'successful',
                //     'currency' => $currency,
                //     'channel' => $channel,
                //     'type' => 'campaign_payment_refund',
                //     'description' => 'Campaign Payment Refund for '.$workSubmitted->campaign->post_title,
                //     'tx_type' => 'Credit',
                //     'user_type' => 'regular'
                // ]);



                $subject = 'Job Denied';
                $status = 'Denied';
                // Mail::to($workSubmitted->user->email)->send(new ApproveCampaign($workSubmitted, $subject, $status));
                // return back()->with('success', 'Campaign has been denied');
                $data['decision_status'] = 'Campaign Denied';
                $data['work'] = $workSubmitted;
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Decision submitted', 'data' => $data], 201);
    }

    public function viewResponse($id)
    {
        try {

            $res = CampaignWorker::where('id', $id)->where('status', 'Pending')->first();
            if (!$res) {
                return response()->json(['status' => false, 'message' => 'Invalid Response'], 401);
            }
            $camp = Campaign::where('id', $res->campaign_id)->first(['id', 'post_title', 'description', 'proof', 'campaign_amount']);

            $data['campaignInfo'] = $camp;
            $data['response'] = $res;
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Activities', 'data' => $data], 200);
    }
