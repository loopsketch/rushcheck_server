<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShareTest extends TestCase
{

    public function test_api_start_host_mode()
    {
        $data = [
            'user_code' => 'host',
            'mode' => 0,
        ];
        $res = $this->post('/api/v/0/share/start', $data);
        // $res->dump();

        $res->assertStatus(200)->assertJson([
            'result' => 'ok',
        ]);
        $workspace = json_decode($res['workspace']);
        $this->assertEquals('host', $workspace->member[0]->user_code);
        $this->assertEquals(0, $workspace->member[0]->mode);
    }

    public function test_api_start_guest_mode()
    {
        // いきなりguestがアクセスできない
        $data = [
            'user_code' => 'guest',
            'mode' => 1,
        ];
        $res = $this->post('/api/v/0/share/start', $data);
        // $res->dump();

        $res->assertStatus(200)->assertJson([
            'result' => 'ng',
        ]);

        // ホストがworkspaceを作成
        $data = [
            'user_code' => 'host',
            'mode' => 0,
        ];
        $res = $this->post('/api/v/0/share/start', $data);
        // $res->dump();

        $res->assertStatus(200)->assertJson([
            'result' => 'ok',
        ]);
        $workspace = json_decode($res['workspace']);

        // guest
        $data = [
            'workspace_code' => $workspace->workspace_code,
            'user_code' => 'guest',
            'mode' => 1,
        ];
        $res = $this->post('/api/v/0/share/start', $data);
        $res->dump();

        $res->assertStatus(200)->assertJson([
            'result' => 'ok',
        ]);
        $workspace = json_decode($res['workspace']);
        $this->assertEquals(2, count($workspace->member));
    }
}
