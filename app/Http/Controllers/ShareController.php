<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use \Exception;

class ShareController extends Controller
{
    /**
     * 
     */
    function start(Request $request)
    {
        $result = [
            'result' => 'ng',
        ];

        DB::beginTransaction();
        try {
            $user_code = $request->user_code;
            $mode = $request->mode;
            $user = (object)[
                'user_code' => $user_code,
                'mode' => $mode,
            ];
            $workspace = null;
            switch ($mode) {
                case 0:
                    for ($retry = 0; is_null($workspace) && $retry < 3; $retry++) {
                        $workspace_code = substr(base_convert(hash('sha256', uniqid()), 16, 36), 0, 4); // 4文字でIDを生成
                        $workspace = Workspace::where('code', $workspace_code)->first();
                        if (is_null($workspace)) {
                            $data = (object)[
                                'workspace_code' => $workspace_code,
                                'member' => [$user],
                            ];
                            $workspace = Workspace::firstOrCreate([
                                'code' => $workspace_code,
                                'data' => json_encode($data),
                            ]);
                        }
                    };
                    if (is_null($workspace)) {
                        $result['error'] = 'failed not create new workspace';
                    }
                    break;
                case 1:
                    $workspace_code = $request->workspace_code;
                    $workspace = Workspace::where('code', $workspace_code)->first();
                    if (is_null($workspace)) {
                        $result['error'] = 'not found workspace code:'. $workspace_code;
                    } else {
                        $data = json_decode($workspace->data);
                        $count = 0;
                        $test_user_code = $user_code;
                        do {
                            $exists_user = collect($data->member)->where('user_code', $test_user_code)->first();
                            if ($exists_user) {
                                $count++;
                                $test_user_code = $user_code. $count;
                            }
                        } while (!is_null($exists_user));
                        if ($count > 0) {
                            $user->user_code = $test_user_code;
                            $user_code = $test_user_code;
                        }

                        $data->member = collect($data->member)->push($user);
                        $workspace->data = json_encode($data);
                        $workspace->save();
                    }
                    break;
            }

            // in guest user logout case, throw user_updated event
            $event = (object)[
                'navigator' => 'user_updated',
                'user' => $user_code,
            ];
            WorkspaceEvent::create([
                'workspace_code' => $workspace_code,
                'data' => json_encode($event),
            ]);

            $result['result'] = is_null($workspace)? 'ng': 'ok';
            if ($workspace) {
                $result['workspace'] = json_decode($workspace->data);
                $result['user'] = $user;
            }
            DB::commit();
        } catch (Exception $ex) {
            $result['error'] = $ex->getMessage();
            Log::warning(url()->current().':'. $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            DB::rollBack();
        }

        return response(json_encode($result), 200)
            ->header('Content-Type', 'application/json');
    }


    /**
     * 
     */
    function stop(Request $request)
    {
        $result = [
            'result' => 'ng'
        ];

        DB::beginTransaction();
        try {
            $workspace_code = $request->workspace_code;
            $user_code = $request->user_code;
            $workspace = Workspace::where('code', $workspace_code)->first();
            if (is_null($workspace)) {
                $result['error'] = 'not found workspace code:'. $workspace_code;
            } else {
                $data = json_decode($workspace->data);
                $user = collect($data->member)->where('user_code', $user_code)->first();
                if ($user) {
                    switch ($user->mode) {
                        case 0:
                            WorkspaceEvent::where('workspace_code', $workspace_code)->delete();
                            $workspace->delete();
                            Log::debug("stop workspace:{$workspace_code}");
                            break;
                        case 1:
                            $data->member = collect($data->member)->filter(fn($user) => $user->user_code != $user_code);
                            $workspace->data = json_encode($data);
                            $workspace->save();
                            Log::debug("remove member:{$user_code} in workspace:{$workspace_code}");
                            break;
                    }
                    $result['result'] = 'ok';

                    // in guest user logout case, throw user_updated event
                    $event = (object)[
                        'navigator' => 'user_updated',
                        'user' => $user_code,
                    ];
                    WorkspaceEvent::create([
                        'workspace_code' => $workspace_code,
                        'data' => json_encode($event),
                    ]);
                }
            }
            DB::commit();
        } catch (Exception $ex) {
            $result['error'] = $ex->getMessage();
            Log::warning(url()->current().':'. $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            DB::rollBack();
        }

        return response(json_encode($result), 200)
            ->header('Content-Type', 'application/json');
    }


    /**
     * 
     */
    function push_event(Request $request)
    {
        Log::debug('push event');
        $result = [
            'result' => 'ng'
        ];

        DB::beginTransaction();
        try {
            $workspace_code = $request->workspace_code;
            $data = json_decode($request->data);
            $workspace = Workspace::where('code', $workspace_code)->first();
            if (is_null($workspace)) {
                $result['error'] = 'not found workspace code:'. $workspace_code;
            } else {
                WorkspaceEvent::create([
                    'workspace_code' => $workspace_code,
                    'data' => json_encode($data),
                ]);
                $result['result'] = 'ok';
            }
            DB::commit();
        } catch (Exception $ex) {
            $result['error'] = $ex->getMessage();
            Log::warning(url()->current().':'. $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            DB::rollBack();
        }

        return response(json_encode($result), 200)
            ->header('Content-Type', 'application/json');
    }

    /**
     * 
     */
    function wait_events(Request $request)
    {
        Log::debug('wait events');
        $result = [
            'result' => 'ng'
        ];

        try {
            $workspace_code = $request->workspace_code;
            $workspace = Workspace::where('code', $workspace_code)->first();
            if (is_null($workspace)) {
                $result['error'] = 'not found workspace code:'. $workspace_code;
            } else {
                $last_id = $request->last_id?? null;

                $result['result'] = 'ok';
                $result['events'] = [];

                // long polling
                Log::debug('start time '.Carbon::now());
                $limit = Carbon::now()->addSeconds(30);
                while (Carbon::now()->lte($limit)) {
                    $query = WorkspaceEvent::where('workspace_code', $workspace_code);
                    if (!is_null($last_id)) {
                        $query->where('id', '>', $last_id);
                    }
                    $events = $query->orderBy('created_at')->get();
                    if ($events->isNotEmpty()) {
                        $result['events'] = $events->map(function($item) {
                            $d = json_decode($item->data);
                            $d->id = $item->id;
                            $d->created_at = $item->created_at;
                            return $d;
                        });
                        break;
                    }
                    usleep(10000); // 10ms
                }

                // refresh workspace data
                $workspace->refresh();
                $data = json_decode($workspace->data);
                $result['workspace'] = $data;
                Log::debug('end time '.Carbon::now());
            }
        } catch (Exception $ex) {
            $result['error'] = $ex->getMessage();
            Log::warning(url()->current().':'. $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }

        return response(json_encode($result), 200)
            ->header('Content-Type', 'application/json');
    }
}
