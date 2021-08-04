<?php

namespace App\Http\Controllers;

use App\Events\RoomEvent;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;

class MainController extends Controller
{
    // topページ モード選択画面
    public function index(Room $room) {
        $room = $room->find(1);
        return view('main.index', compact('room'));
    }
    // ボット
    public function bot(Room $room) {
        $room = $room->find(1);
        return view('main.bot', compact('room'));
    }
    // 二人オフライン対戦
    public function double(Room $room) {
        $room = $room->find(2);
        return view('main.double', compact('room'));
    }
    // 二人オンライン対戦
    // 対戦相手待ちリスト
    public function onlineList() {
        return view('main.list');
    }
    // ルーム作成
    public function roomCreate(Room $room, Request $request, User $user) {
        // ルームを作成
        $room = $room->free();
        // 状態を変更
        $room = $room->fill([
            'is_wait' => 1,
            'mode_id' => 3,
        ]);
        $room->save();
        $user = $user->join_room($room);
        $request->session()->put('room_id', $room->id);
        $request->session()->put('is_join', 1);
        $request->session()->put('color', 1);
        return redirect()->route('onlineWait', ['room_id' => $room->id]);
    }
    // ルーム　待機所
    public function onlineWait() {
        return view('main.wait');
    }
    // オンライン対戦画面
    public function onlineBattle(Request $request, Room $room) {
        $users = $room->users;
        return view('main.online');
    }
    // 対戦ルーム参加
    public function onlineJoin(Request $request, Room $room) {
        $room_id = $request->room_id;
        $room = $room->join($room_id,$request);
        if(empty($room)) {
            return redirect()->route('onlineList')->with('message', 'そのルームは現在ありません。');
        }
        $request->session()->put('room_id', $room->id);
        $request->session()->put('is_join', 1);
        $request->session()->put('color', 2);
        broadcast(new RoomEvent);
        return redirect()->route('onlineBattle');
    }
    // 待機画面　退出
    public function onlineLeave(Room $room) {

        
        // ユーザーのroom_idを削除する　　
        // ...code


        $room_id = session('room_id');
        $room->destroy($room_id);
        session()->forget('room_id');
        session()->forget('color');
        session()->forget('is_join');
        return redirect()->route('onlineList');
    }
    // リセット
    public function reset(Room $room) {
        $reversi[3][3] = 1;
        $reversi[3][4] = 2;
        $reversi[4][3] = 2;
        $reversi[4][4] = 1;
        $rooms = $room->all();
        foreach($rooms as $r) {
            $board = $r->board;
            $board->fillContent($reversi);
        }   
        return redirect()->back();
    }
    // テスト
    public function test(Request $request) {
        dd(session()->getId());
        $data = [];
        $count = 0;
        while($count < 10) {
            if($count == 5) {
                break;
            }
            $data[] = $count;
            $count++;
        }
        return view('main.test');
    }
}
