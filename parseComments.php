<?php

namespace App\Console\Commands;

use App\Cars;
use App\CarsComments;
use App\Parse as ParseModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class parseComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:comments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        /** @var Collection $activeTasks */
        $activeTasks = ParseModel::query()
            ->where('status', ParseModel::ACTIVE)
            ->get();

        $activeTasks->each(function ($task) {
            Cars::query()
                ->where('task_id', $task->id)
                ->chunk(500, function (Collection $cars) use ($task) {
                    $cars->each(function ($car) use ($task) {

                        $this->parseComments($car, $task);
                        $this->parseObmen($car, $task);
                    });
                });
        });
    }

    /**
     * @param $car
     * @param $task
     */
    public function parseComments($car, $task): void
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            // 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            // 
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $comments = json_decode(curl_exec($curl), 1);

        if (!empty($comments['data']['info']['wall']['comments']['propose'])) {
            foreach ($comments['data']['info']['wall']['comments']['propose'] as $comment) {
                $commentedUserId = $comment['user']['id'];

                $commentExist = CarsComments::query()
                    ->where('commented_user_id', $commentedUserId)
                    ->where('task_id', $task->id)
                    ->get();

                if ($commentExist->isEmpty()) {
                    curl_setopt_array($curl, array(
                        // 
                    ));

                    $commentedUserData = json_decode(curl_exec($curl), 1);

                    $commentedUserPhones = [];

                    foreach ($commentedUserData['data']['user']['phones'] as $phones) {
                        $commentedUserPhones[] = $phones['value'];
                    }

                    $commentModel = new CarsComments;

                    $commentModel->advert_id = $car->advert_id;
                    $commentModel->name = $commentedUserData['data']['user']['name'];
                    $commentModel->commented_user_id = $commentedUserId;
                    $commentModel->phones = implode(',', $commentedUserPhones);
                    $commentModel->text = $comment['text'];
                    $commentModel->date_time = Carbon::parse($comment['date']);
                    $commentModel->task_id = $task->id;

                    $commentModel->save();
                }
            }
        }
    }

    /**
     * @param $car
     * @param $task
     */
    public function parseObmen($car, $task)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
// 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $comments = json_decode(curl_exec($curl), 1);

        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $commentedUserId = $comment['userId'];

                $commentExist = CarsComments::query()
                    ->where('commented_user_id', $commentedUserId)
                    ->where('task_id', $task->id)
                    ->get();

                if ($commentExist->isEmpty()) {
                    curl_setopt_array($curl, array(
// 
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        // 
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json"
                        ),
                    ));

                    $commentedUserData = json_decode(curl_exec($curl), 1);
                    $commentedUserPhones = [];

                    foreach ($commentedUserData['data']['user']['phones'] as $phones) {
                        $commentedUserPhones[] = $phones['value'];
                    }
                    if (array_key_exists('price', $comment)) {
                        $commentModel = new CarsComments;

                        $commentModel->advert_id = $car->advert_id;
                        $commentModel->name = $commentedUserData['data']['user']['name'];
                        $commentModel->commented_user_id = $commentedUserId;
                        $commentModel->phones = implode(',', $commentedUserPhones);
                        $commentModel->text = $comment['priceWallAnswer']. ' '. $comment['price'] . ' '. ($comment['link'] ?? '');
                        $commentModel->date_time = Carbon::parse($comment['addDate']);
                        $commentModel->task_id = $task->id;

                        $commentModel->save();
                    }
                }
            }
        }
    }


}
