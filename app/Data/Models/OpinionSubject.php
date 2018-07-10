<?php
namespace App\Data\Models;

use App\Data\Presenters\OpinionSubjectPresenter;

use Kalnoy\Nestedset\NodeTrait;

class OpinionSubject extends BaseModel
{
    use NodeTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'opinion_subjects';

    /**
     * @var array
     */
    protected $fillable = ['name'];

    protected $presenters = ['edit_link'];

    public function getPresenterClass()
    {
        return OpinionSubjectPresenter::class;
    }
}