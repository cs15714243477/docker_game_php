<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class ClubPromoterMain extends Model
{
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promoter_main';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}