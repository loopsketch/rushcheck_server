<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceEvent;
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
                        }

                        $data->member = collect($data->member)->push($user);
                        $workspace->data = json_encode($data);
                        $workspace->save();
                    }
                    break;
            }
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
        $result = [
            'result' => 'ng'
        ];

        DB::beginTransaction();
        try {
            $workspace_code = $request->workspace_code;
            // $user_code = $request->user_code;
            $data = json_decode($request->data);
            $workspace = Workspace::where('code', $workspace_code)->first();
            if (is_null($workspace)) {
                $result['error'] = 'not found workspace code:'. $workspace_code;
            } else {
                // $data = $workspace->data;
                // $user = collect($data->member)->where('user_code', $user_code)->first();
                // if ($user && $user->mode == 0) {
                    WorkspaceEvent::create([
                        'workspace_code' => $workspace_code,
                        'data' => json_encode($data),
                    ]);
                    $result['result'] = 'ok';
                // } else {
                //     $result['error'] = 'invalid user';
                // }
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
        $result = [
            'result' => 'ng'
        ];

        try {
            $workspace_code = $request->workspace_code;
            //$user_code = $request->user_code;
            $workspace = Workspace::where('code', $workspace_code)->first();
            if (is_null($workspace)) {
                $result['error'] = 'not found workspace code:'. $workspace_code;
            } else {
                $data = json_decode($workspace->data);
                //$user = collect($data->member)->where('user_code', $user_code)->first();
                //if ($user) {
                    $last_id = $request->last_id?? null;
                    $query = WorkspaceEvent::where('workspace_code', $workspace_code);
                    if (!is_null($last_id)) {
                        $query->where('id', '>', $last_id);
                    }
                    $events = $query->orderBy('created_at')->get();
                    $result['result'] = 'ok';
                    $result['events'] = $events->map(function($item) {
                        $data = json_decode($item->data);
                        $data->id = $item->id;
                        $data->created_at = $item->created_at;
                        return $data;
                    });
                    $result['workspace'] = $data;
                // } else {
                //     $result['error'] = 'invalid user';
                // }
            }
        } catch (Exception $ex) {
            $result['error'] = $ex->getMessage();
            Log::warning(url()->current().':'. $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }

        return response(json_encode($result), 200)
            ->header('Content-Type', 'application/json');
    }
}
