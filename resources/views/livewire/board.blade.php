<div>
    {{-- A good traveler has no fixed plans and is not intent upon arriving. --}}
    @isset($message)
        <div class="component_alert danger">{{ $message }}</div>
    @endisset
    {{-- 色処理 --}}
    @php
        if($color == 1) {
            $color_txt = '黒';
        } elseif ($color == 2) {
            $color_txt = '白';
        }
    @endphp
    {{-- 勝者 --}}
    @isset($winner)
        @php
            if($winner == $color) {
                $judge = '勝ち';
            } elseif ($winner == 3) {
                $judge = '引き分け';
            } else {
                $judge = '負け';
            }
        @endphp
        <div class="component_cover black">
            <div class="component_cover_child">
                <p>{{ $finish_message }}</p>
                <p>勝敗：{{ $judge }}</p>
                <button wire:click="finish_btn" class="component_btn danger">終了</button>
            </div>
        </div>
    @endisset
    {{-- 相手待ち --}}
    @empty($enemy)
        <div wire:poll.5000ms="no_enemy" class="component_cover black">
            <div class="component_cover_child">
                <p>対戦相手と接続中</p>
                <div class="component_load_circle"></div>
            </div>
        </div>
    @endempty
    {{-- next_color --}}
    @if ($next_color != $color)
        <div class="component_cover black"></div>
    @endif
    <button wire:click="surrender" class="component_btn danger">投了</button>
    <h1>あなたの色は{{ $color_txt }}</h1>
    {{-- @foreach ($users as $user)
        @if ($user->id == )
            
        @else
            
        @endif
    @endforeach --}}
    <table class="table">
        @for ($i1 = 0; $i1 < 8; $i1++)
        <tr data-tr-index='{{ $i1 }}'>
            @for ($i2 = 0; $i2 < 8; $i2++)
                @if (isset($content[$i1][$i2]) && $content[$i1][$i2] == 1)
                    @if (!empty($puttedCoord[0]) && $puttedCoord[0] == $i1 && $puttedCoord[1] == $i2)
                        <td class="black put" data-td-index="{{ $i2 }}">●</td>
                    @else
                        <td class="black" data-td-index="{{ $i2 }}">●</td>
                    @endif
                @elseif (isset($content[$i1][$i2]) && $content[$i1][$i2] == 2)
                    @if (!empty($puttedCoord[0]) && $puttedCoord[0] == $i1 && $puttedCoord[1] == $i2)
                        <td class="white put" data-td-index="{{ $i2 }}">○</td>
                    @else
                        <td class="white" data-td-index="{{ $i2 }}">○</td>
                    @endif
                @else
                    @php
                        $next_flag = false;
                        if(isset($nexts)) {
                            foreach ($nexts as $next) {
                                if($next[0] == $i1 && $next[1] == $i2) {
                                    $next_flag = true;
                                }
                            }
                        }
                    @endphp
                    {{-- nextflag --}}
                    @if ($next_flag)
                        <td class="next" wire:click='put({{ $i1 }}, {{ $i2 }})' data-td-index="{{ $i2 }}"></td>
                    @else
                        <td wire:click='put({{ $i1 }}, {{ $i2 }})' data-td-index="{{ $i2 }}"></td>
                    @endif
                @endif
            @endfor
        </tr>
        @endfor
    </table>
    {{-- 時間切れ --}}
    @isset($has_time)
        <div wire:poll.{{ $has_time }}s="time_over" class=""></div>
    @endisset
    <a class="component_btn danger" href="{{ route('reset') }}">リセット</a>
    @isset($pass)
        <button wire:click="pass" class="component_btn primary">パス</button>
    @endisset
</div>