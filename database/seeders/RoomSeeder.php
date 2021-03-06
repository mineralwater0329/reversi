<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('rooms')->truncate();
        DB::table('rooms')->insert([
            'board_id' => 1,
            'mode_id' => 1,
        ]);
        DB::table('rooms')->insert([
            'board_id' => 2,
            'mode_id' => 2,
        ]);
    }
}
