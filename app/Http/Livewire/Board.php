<?php

namespace App\Http\Livewire;

use App\Events\FinishEvent;
use App\Events\PassEvent;
use App\Events\PublicEvent;
use App\Events\PutEvent;
use App\Events\SurrenderEvent;
use App\Http\Logic\LivewireLogic;
use App\Http\Logic\RequestLogic;
use App\Models\Room;
use Livewire\Component;
use Livewire\Livewire;

class Board extends Component
{
    public $puttedCoord = [];
    public $color;
    public $room_id;
    public $message;
    public $content;
    public $nexts;
    public $pass;
    public $winner;
    public $next_color;
    public $finish_message;
    public $enemy = false;
    public $has_time;
    public $start_time;
    // リスナー(websocketを含む)
    public function getListeners() {
        $events = [
            "echo:laravel_database_private-battle.{$this->room_id},PutEvent" => 'enemy_putted',
            "echo:laravel_database_private-battle.{$this->room_id},PassEvent" => 'enemy_pass',
            "echo:laravel_database_private-battle.{$this->room_id},FinishEvent" => 'finish',
            "echo:laravel_database_private-battle.{$this->room_id},SurrenderEvent" => 'finish',
            "echo-presence:presence.{$this->room_id},here" => 'here',
            "echo-presence:presence.{$this->room_id},joining" => 'enemy_join',
            "echo-presence:presence.{$this->room_id},leaving" => 'enemy_leave',
            // "echo-presence:presence.{$this->room_id},listen, PresenceEvent" => 'p_listen',
            'put', // 消す
            'pass', // 消す
        ];
        return $events;
    }
    public function mount()
    {
        $user = auth()->user();
        $this->color = $user->color;
        $this->room_id = $user->room_id;
        $board = $user->room->board;
        $this->content = $board->getContent();
        $this->next_color = $board->next_color;
        if($this->next_color == $this->color) {
            $this->nexts();
        }
    }
    public function put($i1, $i2) {
        $this->puttedCoord = [$i1,$i2];
        $Logic = new LivewireLogic;
        $results = $Logic->put($i1, $i2, $this->color, $this->room_id);
        if(isset($results['problem'])) {
            return $this->message = 'そこには置けません';
        } else {
            $this->data_reset();
            $this->content = $results['content'];
            $this->has_time = null;
            broadcast(new PutEvent([$i1, $i2], $this->content, $this->start_time))->toOthers();
            $this->turn_next_color();
            if($results['finish']) {
                $data = [
                    'content' => $this->content,
                ];
                broadcast(new FinishEvent($data));
            }
        }
    }
    public function pass() {
        $this->data_reset();
        $Logic = new LivewireLogic;
        $Logic->pass($this->color);
        $this->has_time = null;
        broadcast(new PassEvent($this->start_time))->toOthers();
        $this->turn_next_color();
    }
    public function enemy_putted($data) {
        $this->content = $data['content'];
        $this->puttedCoord = $data['puttedCoord'];
        $this->turn_next_color();
        $this->nexts();
    }
    public function enemy_pass() {
        $this->turn_next_color();
        $this->nexts();
    }
    public function enemy_join() {
        $this->enemy = true;
        $this->set_times();
    }
    public function enemy_leave() {
        $room = auth()->user()->room;
        if(isset($room) && empty($room->board->winner)) {
            $room->finish($this->color);
            $this->finish([
                'winner' => $this->color,
                'message' => '敵が接続切れしました',
            ]);
        }
    }
    public function nexts() {
        $Logic = new LivewireLogic;
        $nexts = $Logic->nexts($this->color, $this->content);
        if(empty($nexts)) {
            $this->pass = true;
            // $this->emit('pass'); // 消す
        } else {
            $this->nexts = $nexts;
            $this->emit('put',$this->nexts[0][0], $this->nexts[0][1]); // 消す
        }
        // 時間
        $this->set_times();
    }
    public function finish($data) {
        $this->next_color = $this->color;
        $this->winner = $data['winner'];
        $this->finish_message = $data['message'];
        $this->user_data_delete();
        $this->data_reset();
    }
    public function surrender() {
        if($this->next_color == $this->color) {
            broadcast(new SurrenderEvent);
        }
    }
    // プレゼンスチャンネル設定
    public function here($users) {
        if(count($users) == 1) {
            $this->enemy = false;
        } else {
            $this->enemy = true;
            sleep(1);
            $this->set_times();
            $room = auth()->user()->room;
            if(isset($room->board->winner)) {
                $winner_color = $this->winner_color($room->board->winner, $room->board);
                $data = [$winner_color, '接続切れであなたの負けです'];
                $finish_data = [
                    'winner' => $data[0],
                    'message' => $data[1],
                ];
                $this->enemy = true;
                $this->finish($finish_data);
            }
        }
    }
    public function no_enemy() {
        if(empty($this->enemy)) {
            $room = auth()->user()->room;
            if(isset($room->board->winner)) {
                $winner_color = $this->winner_color($room->board->winner, $room->board);
                $data = [$winner_color, 'すでに勝負は終わっています'];
            } else {
                $data = [3 , '相手の通信エラーのため引き分け'];
            }
            $finish_data = [
                'winner' => $data[0],
                'message' => $data[1],
            ];
            $this->enemy = true;
            $this->finish($finish_data);
            // room削除処理
            $room->is_battle = 0;
            $room->save();
        }
    }
    public function time_over() {
        $room = auth()->user()->room;
        // 勝負が決まっていなかったら
        if(empty($room->board->winner)) {
            $Logic = new RequestLogic;
            $winner_color = $Logic->turnColor($this->color);
            $data = [
                'flag' => true,
                'winner' => $winner_color,
                'message' => '時間切れで終了です',
            ];
            broadcast(new FinishEvent($data));
        }
        $this->has_time = null;
    }
    public function finish_btn() {
        return redirect()->route('onlineList');
    }
    public function data_reset() {
        $this->reset(['message', 'nexts', 'pass', 'has_time']);
    }
    public function user_data_delete() {
        $user = auth()->user();
        $user->room_id = null;
        $user->color = null;
        $user->time = null;
        $user->save();
    }
    public function turn_next_color() {
        $Logic = new RequestLogic;
        $this->next_color = $Logic->turnColor($this->next_color);
    }
    public function winner_color($winner, $board) {
        if($board->user1 == $winner) {
            $winner_color = 1;
        } elseif($board->user2 == $winner) {
            $winner_color = 2;
        }
        return $winner_color;
    }
    public function set_times() {
        if(isset($this->color) && isset($this->next_color)) {
            if($this->color == $this->next_color) {
                $this->has_time = auth()->user()->time;
                $this->start_time = time();
            }
        }
    }
    public function render()
    {
        return view('livewire.board');
    }
}