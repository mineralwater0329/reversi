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
    public $mode_id;
    public $message;
    public $content;
    public $nexts;
    public $pass;
    public $winner;
    public $next_color;
    public $finish_message;
    public $enemy = false;
    public $has_time;
    public $enemy_has_time;
    public $start_time;
    public $players;
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
        $this->mode_id = $user->room->mode_id;
        $board = $user->room->board;
        $this->content = $board->getContent();
        $this->next_color = $board->next_color;
    }
    public function put($i1, $i2) {
        $this->puttedCoord = [$i1,$i2];
        $Logic = new LivewireLogic;
        $results = $Logic->put($i1, $i2, $this->color, $this->room_id);
        if(isset($results['problem'])) {
            $user = auth()->user();
            $this->has_time = $Logic->diff_time($this->start_time, $user->time);
            $this->enemy_has_time = $this->get_enemy_time();
            return $this->message = 'そこには置けません';
        } else {
            $this->data_reset();
            $this->content = $results['content'];
            broadcast(new PutEvent([$i1, $i2], $this->content))->toOthers();
            $this->update_time();
            $this->turn_next_color();
            if($results['finish']) {
                $data = [
                    'content' => $this->content,
                ];
                broadcast(new FinishEvent($data));
            }
        }
        $this->nexts();
    }
    public function pass() {
        $this->data_reset();
        $Logic = new LivewireLogic;
        $Logic->pass($this->color);
        broadcast(new PassEvent())->toOthers();
        $this->update_time();
        $this->turn_next_color();
        $this->nexts();
    }
    public function enemy_putted($data) {
        $this->content = $data['content'];
        $this->puttedCoord = $data['puttedCoord'];
        $this->turn_next_color();
        $this->set_my_times();
        $this->nexts();
    }
    public function enemy_pass() {
        $this->turn_next_color();
        $this->set_my_times();
        $this->nexts();
    }
    public function enemy_join() {
        $this->enemy = true;
        $room = auth()->user()->room;
        if(isset($room) && empty($room->board->winner)) {
            $this->set_players();
            $this->set_my_times();
            $users_times = $this->get_users_times();
            $this->emit('js_times', $users_times);
            $this->nexts();
        }
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
        $board = auth()->user()->room->board;
        $nexts = $board->next_coords;
        $nexts = json_decode($nexts);
        if(empty($nexts)) {
            $this->pass = true;
            $this->reset(['nexts']);
            // $this->emit('pass'); // 消す
        } else {
            $this->nexts = $nexts;
            // $this->emit('put',$this->nexts[0][0], $this->nexts[0][1]); // 消す
        }
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
            // ネクスト初期設定
            $board = auth()->user()->room->board;
            // すでに勝負がついていたら
            if(isset($board->winner)) {
                // no_enemyを発生させないため
                $this->enemy = true;
                $winner_color = $this->winner_color($board->winner, $board);
                $this->finish([
                    'winner' => $winner_color, 
                    'message' => 'すでに勝負はついています'
                ]);
            }
            if(isset($board)) {
                $next_color = $board->next_color;
                $Logic = new LivewireLogic;
                $content = $board->getContent();
                $nexts = $Logic->next_nexts($next_color, $content);
                $json_nexts = json_encode($nexts);
                $board->fill(['next_coords' => $json_nexts])->save();
            }
        } else {
            $this->enemy = true;
            sleep(1);
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
            } else {
                $this->set_players();
                $this->set_my_times();
                $users_times = $this->get_users_times();
                $this->emit('js_times', $users_times);
                $this->nexts();
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
            if(isset($room)) {
                $room->is_battle = 0;
                $room->save();
            }
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
        session()->flash('alert' , ['flag' => 1, 'message' => '終了しました']);
        return redirect()->route('onlineList');
    }
    public function data_reset() {
        $this->reset(['message', 'nexts', 'pass']);
    }
    public function user_data_delete() {
        $this->reset(['has_time', 'enemy_has_time']);
        $this->start_time = null;
        $user = auth()->user();
        $user->room_id = null;
        $user->color = null;
        $user->time = null;
        $user->save();
    }
    public function turn_next_color() {
        $this->reset(['start_time']);
        $board = auth()->user()->room->board;
        $this->next_color = $board->next_color;
        if(isset($this->next_color)) {
            $users_times = $this->get_users_times();
            $this->emit('js_times', $users_times);
        }
        // コマのセット
        $Logic = new RequestLogic;
        $data = $Logic->judge($board->getContent());
        if($this->players[0]['color'] == 1) {
            $this->players[0]['count'] = $data['counts'][1];
            $this->players[1]['count'] = $data['counts'][2];
        } else {
            $this->players[1]['count'] = $data['counts'][2];
            $this->players[0]['count'] = $data['counts'][1];
        }
    }
    public function winner_color($winner, $board) {
        if($board->user1 == $winner) {
            $winner_color = 1;
        } elseif($board->user2 == $winner) {
            $winner_color = 2;
        }
        return $winner_color;
    }
    public function set_my_times() {
        if(isset($this->color) && isset($this->next_color)) {
            $this->has_time = auth()->user()->time;
            if($this->color == $this->next_color) {
                $this->start_time = time();
            }
        }
    }
    public function get_users_times() {
        $users_times['my_time'] = $this->has_time;
        $enemy_time = $this->get_enemy_time();
        $users_times['enemy_time'] = $enemy_time;
        $my_turn = false;
        if($this->next_color === $this->color) {
            $my_turn = true;
        }
        $users_times['my_turn'] = $my_turn;
        return $users_times;
    }
    public function get_enemy_time() {
        $user = auth()->user();
        $users_times['my_time'] = $user->time;
        $Logic = new RequestLogic;
        $enemy_color = $Logic->turnColor($this->color);
        $enemy = $user->room->search_user($user->room->users, $enemy_color);
        return $enemy->time;
    }
    public function update_time() {
        $Logic = new LivewireLogic;
        $user = auth()->user();
        $time = $Logic->diff_time($this->start_time, $user->time);
        $user->time = $time;
        $this->has_time = $time;
        $user->save();
    }
    public function set_players()
    {
        $user = auth()->user();
        if(isset($user)) {
            $room = $user->room;
            if(isset($room)) {
                if($room->users[0]->is_guest_user) {
                    $room->users[0]->name = 'ゲスト';
                    // $room->users[0]->save();
                }
                if($room->users[1]->is_guest_user) {
                    $room->users[1]->name = 'ゲスト';
                    // $room->users[1]->save();
                }
                $this->players = [
                    ['name' => $room->users[0]->name, 'color' => $room->users[0]->color, 'count' => 2],
                    ['name' => $room->users[1]->name, 'color' => $room->users[1]->color, 'count' => 2]
                ];
            }
        }
    }
    public function is_finished() {

    }
    public function render()
    {
        return view('livewire.board');
    }
}
