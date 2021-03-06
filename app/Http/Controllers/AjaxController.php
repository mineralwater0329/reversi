<?php

namespace App\Http\Controllers;

use App\Http\Logic\BotLogic;
use App\Models\Board;
use App\Models\Room;
use Illuminate\Http\Request;
use App\Http\Logic\RequestLogic;
use Illuminate\Support\Facades\Log;

class AjaxController extends Controller
{
    // 置く場所を選択したとき
    public function send(Request $request, Room $room, RequestLogic $requestLogic, BotLogic $botLogic) {
        // ルームをとる
        $room = $room->find($request->room);
        $board = $room->board;
        $content = $board->getContent();
        // ユーザー　白か黒　判断する
        $usercolor = intval($request->color);
        // パスではない時
        if(!$request->pass) {
            // 置かれた座標を取得
            $i1 = $request->i1;
            $i2 = $request->i2;
            //　おけるかチェック
            $changes = $requestLogic->check($i1,$i2,$content,$usercolor);
            // おけない場合
            if(empty($changes)) {
                return response()->json(['problem' => true]);
            }
            // 実際にひっくりかえす
            $content = $requestLogic->reverse($usercolor,$changes,$content,$i1,$i2);
            // データベースに保存
            $board->fillContent($content);
            // 最低限のデータ
            $json = [
                'i1' => $i1,
                'i2' => $i2,
                'user' => $usercolor,
                'changes' => $changes,
            ];
        } else {
            $json = [
                'user' => $usercolor,
            ];
        }
        $next_color = $requestLogic->turnColor($usercolor);
        // 次に置ける場所を特定する
        $nexts = $requestLogic->nextCoords($next_color,$content);
        // 次に置ける場所がない時 パスをtrueにする
        $json = $requestLogic->nextCheck($nexts,$json);
        // モード別条件分岐
        if(isset($room->mode_id)) {
            switch ($room->mode_id) {
                // ボット対戦の時
                case 1:
                    // ボットの操作
                    $json = $botLogic->botnexts($nexts, $board, $usercolor, $content, $json);
                    // 終了かチェック
                    if(isset($json['content'])) {
                        $content = $json['content'];
                    }
                    if(isset($json['finish'])) {
                        $judge = $requestLogic->judge($content);
                        $json['winner'] = $judge['winner'];
                        // ログインしていたら
                        if(auth()->check()) {
                            $winner_id = 0;
                            // 黒が勝者なら
                            if($json['winner'] == 1) {
                                $winner_id = auth()->user()->id;
                            }
                            $board->fill(['winner' => $winner_id])->save();
                        }
                    }
                    break;
                    // オフライン対戦
                    case 2:
                        $finish = $requestLogic->judge_finish($content, $next_color);
                        if($finish) {
                            $judge = $requestLogic->judge($content);
                            $json['finish'] = true;
                            $json['winner'] = $judge['winner'];
                        }
                        break;
                    }
                }
        // コマの数を取得
        $judge = $requestLogic->judge($content);
        $json['counts'] = $judge['counts'];
        // Log::debug($json);
        return response()->json($json);
    }
    // パス
    public function pass() {

    }
    // 
}