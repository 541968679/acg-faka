<?php
declare (strict_types=1);

namespace Version335;


use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;

class Update
{

    /**
     * @return void
     */
    public function handle(): void
    {
        $extend = Manager::schema()->hasColumn("user", "wallet_address");
        if (!$extend) {
            Manager::schema()->table("user", function (Blueprint $blueprint) {
                $blueprint->string("wallet_address", 64)->nullable(true)->default(null);
            });
        }
    }
}


