<?php
namespace Model;

use App;
use Exception;
use System\Emerald\Emerald_model;
use stdClass;
use ShadowIgniterException;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 27.01.2020
 * Time: 10:10
 */
class Boosterpack_model extends Emerald_model
{
    const CLASS_TABLE = 'boosterpack';

    /** @var float Цена бустерпака */
    protected $price;
    /** @var float Банк, который наполняется  */
    protected $bank;
    /** @var float Наша комиссия */
    protected $us;

    protected $boosterpack_info;


    /** @var string */
    protected $time_created;
    /** @var string */
    protected $time_updated;

    /**
     * @return float
     */
    public function get_price(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     *
     * @return bool
     */
    public function set_price(int $price):bool
    {
        $this->price = $price;
        return $this->save('price', $price);
    }

    /**
     * @return float
     */
    public function get_bank(): float
    {
        return $this->bank;
    }

    /**
     * @param float $bank
     *
     * @return bool
     */
    public function set_bank(float $bank):bool
    {
        $this->bank = $bank;
        return $this->save('bank', $bank);
    }

    /**
     * @return float
     */
    public function get_us(): float
    {
        return $this->us;
    }

    /**
     * @param float $us
     *
     * @return bool
     */
    public function set_us(float $us):bool
    {
        $this->us = $us;
        return $this->save('us', $us);
    }

    /**
     * @return string
     */
    public function get_time_created(): string
    {
        return $this->time_created;
    }

    /**
     * @param string $time_created
     *
     * @return bool
     */
    public function set_time_created(string $time_created):bool
    {
        $this->time_created = $time_created;
        return $this->save('time_created', $time_created);
    }

    /**
     * @return string
     */
    public function get_time_updated(): string
    {
        return $this->time_updated;
    }

    /**
     * @param string $time_updated
     *
     * @return bool
     */
    public function set_time_updated(string $time_updated):bool
    {
        $this->time_updated = $time_updated;
        return $this->save('time_updated', $time_updated);
    }

    //////GENERATE

    /**
     * @return Boosterpack_info_model[]
     */
    public function get_boosterpack_info(): array
    {
        if (is_null($this->boosterpack_info)) {
            $this->boosterpack_info = Boosterpack_info_model::get_by_boosterpack_id($this->get_id());
        }
        return $this->boosterpack_info;
    }

    function __construct($id = NULL)
    {
        parent::__construct();

        $this->set_id($id);
    }

    public function reload()
    {
        parent::reload();
        return $this;
    }

    public static function create(array $data)
    {
        App::get_s()->from(self::CLASS_TABLE)->insert($data)->execute();
        return new static(App::get_s()->get_insert_id());
    }

    public function delete():bool
    {
        $this->is_loaded(TRUE);
        App::get_s()->from(self::CLASS_TABLE)->where(['id' => $this->get_id()])->delete()->execute();
        return App::get_s()->is_affected();
    }

    public static function get_all()
    {
        return static::transform_many(App::get_s()->from(self::CLASS_TABLE)->many());
    }

    /**
     * @param User_model $user
     * @return int
     */
    public function open(User_model $user): int
    {
        if ($user->get_wallet_balance() < $this->get_price()) {
            return 0;
        }

        $max_price = ($this->get_bank() + $this->get_price() - $this->get_us());

        if (! $available_items = $this->get_contains((int) $max_price)) {
            return 0;
        }

        $random_item = $available_items[array_rand($available_items)];
        $user_new_likes_balance = $user->get_likes_balance() + $random_item->get_price();
        $new_bank = $this->get_bank() + $this->get_price() - $this->get_us() - $random_item->get_price();

        // Start of Transaction
        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        try {
            $user_money_charged = $user->remove_money((float)$this->get_price());
            $user_likes_balance_updated = $user->set_likes_balance($random_item->get_price());
            $bank_updated = $this->set_bank($new_bank);
            $action_logged = Analytics_model::create([
                'user_id' => $user->get_id(),
                'object' => 'boosterpack',
                'action' => 'buy',
                'object_id' => $this->get_id(),
                'amount' => 1
            ]);

            if (
                ! $user_money_charged
                || ! $user_likes_balance_updated
                || ! $bank_updated
                || ! $action_logged
            ) {
                throw new Exception('Transaction canceled.');
            }

            $likes = $random_item->get_price();
            $transaction_success = true;
        } catch (Exception $exception) {
            $likes = 0;
            $transaction_success = false;
        }

        // End of Transaction
        $transaction_success
            ? App::get_s()->commit()->execute()
            : App::get_s()->rollback()->execute();

        return $likes;
    }

    /**
     * @param int $max_available_likes
     *
     * @return Item_model[]
     */
    public function get_contains(int $max_available_likes): array
    {
        $items = [];

        foreach ($this->get_boosterpack_info() as $boosterpack_info) {
            if ($boosterpack_info->get_item()->get_price() <= $max_available_likes) {
                $items[] = $boosterpack_info->get_item();
            }
        }

        return $items;
    }


    /**
     * @param Boosterpack_model $data
     * @param string            $preparation
     *
     * @return stdClass|stdClass[]
     */
    public static function preparation(Boosterpack_model $data, string $preparation = 'default')
    {
        switch ($preparation)
        {
            case 'default':
                return self::_preparation_default($data);
            case 'contains':
                return self::_preparation_contains($data);
            default:
                throw new Exception('undefined preparation type');
        }
    }

    /**
     * @param Boosterpack_model $data
     *
     * @return stdClass
     */
    private static function _preparation_default(Boosterpack_model $data): stdClass
    {
        $o = new stdClass();

        $o->id = $data->get_id();
        $o->price = $data->get_price();

        return $o;
    }


    /**
     * @param Boosterpack_model $data
     *
     * @return stdClass
     */
    private static function _preparation_contains(Boosterpack_model $data): stdClass
    {
        // TODO: task 5, покупка и открытие бустерпака
    }
}
