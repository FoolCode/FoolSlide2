<?php

namespace Foolz\Foolslide\Model;

use Foolz\Foolframe\Model\Config;
use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Model;
use Foolz\Foolframe\Model\Preferences;
use Foolz\Foolframe\Model\Util;
use Foolz\Profiler\Profiler;
use Symfony\Component\Validator\Constraints as Assert;

class ReleaseNotFoundException extends \Exception {}

class ReleaseFactory extends Model
{

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Preferences
     */
    protected $preferences;

    /**
     * @var Profiler
     */
    protected $profiler;

    /**
     * @var SeriesFactory
     */
    protected $series_factory;

    public function __construct(\Foolz\Foolframe\Model\Context $context)
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->preferences = $context->getService('preferences');
        $this->config = $context->getService('config');
        $this->profiler = $context->getService('profiler');
        $this->series_factory = $context->getService('foolslide.series_factory');
    }

    /**
     * @return array The strcuture of the database with info for validation and form creation
     */
    public function getStructure()
    {
        $dc = $this->dc;

        $structure = [
            'open' => ['type' => 'open'],
            'id' => [
                'type' => 'hidden',
                'database' => true,
                'validation_func' => function($input, $form_internal) use ($dc) {
                    // check that the ID exists
                    $row = $dc->qb()
                        ->select('COUNT(*) as count')
                        ->from($dc->p('releases'), 'r')
                        ->where('id = :id')
                        ->setParameter(':id', $input['id'])
                        ->execute()
                        ->fetch();

                    if ($row['count'] != 1) {
                        return [
                            'error_code' => 'ID_NOT_FOUND',
                            'error' => _i('Couldn\'t find the release with the submitted ID.'),
                            'critical' => true
                        ];
                    }

                    return ['success' => true];
                }
            ],
            'series_id' => [
                'type' => 'hidden',
                'database' => true,
                'label' => _i('Series ID'),
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
                'validation_func' => function($input, $form_internal) use ($dc) {
                    // check that the ID exists
                    $row = $dc->qb()
                        ->select('COUNT(*) as count')
                        ->from($dc->p('series'), 's')
                        ->where('id = :id')
                        ->setParameter(':id', $input['series_id'])
                        ->execute()
                        ->fetch();

                    if ($row['count'] != 1) {
                        return [
                            'error_code' => 'ID_NOT_FOUND',
                            'error' => _i('Couldn\'t find the series with the submitted ID.'),
                            'critical' => true
                        ];
                    }

                    return ['success' => true];
                }
            ],
            'volume' => [
                'database' => true,
                'label' => _i('The volume number'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'volume_part' => [
                'database' => true,
                'label' => _i('The part of the volume'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'chapter' => [
                'database' => true,
                'label' => _i('The chapter number'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'chapter_part' => [
                'database' => true,
                'label' => _i('The part of the chapter'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'extra' => [
                'database' => true,
                'label' => _i('Extra sorting'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'language' => [
                'database' => true,
                'label' => _i('The language of the release'),
                'type' => 'input',
                'class' => 'span1',
                'validation' => [new Assert\NotBlank(), new Assert\Type('digit')],
            ],
            'title' => [
                'database' => true,
                'type' => 'input',
                'label' => _i('Name'),
                'help' => _i('Insert the title of the board.'),
                'placeholder' => _i('Required'),
                'class' => 'span3',
                'validation' => [new Assert\NotBlank(), new Assert\Length(['max' => 256])]
            ],
            'separator-2' => ['type' => 'separator-short'],
            'submit' => [
                'type' => 'submit',
                'class' => 'btn-primary',
                'value' => _i('Submit')
            ],
            'close' => ['type' => 'close']
        ];

        return $structure;
    }

    /**
     * Updates or adds a releases in the database
     * The action is decided by the presence of the value ID
     * The data must be already sanitized at this point
     *
     * @param array $data Associative array with the column names as keys, already sanitized
     *
     * @return int The ID of the row
     */
    public function save(array $data)
    {
        $dc = $this->dc;

        if (isset($data['id'])) {
            // update values
            $update = $dc->qb()
                ->update($dc->p('releases'), 's');

            foreach ($data as $k => $i) {
                $update->set($k, $dc->getConnection()->quote($i));
            }

            $update->where('id = :id')
                ->setParameter(':id', $data['id'])
                ->execute();

            $id = $data['id'];

        } else {
            // insert new release
            $dc->getConnection()
                ->insert($dc->p('releases'), $data);

            $id = $dc->getConnection()->lastInsertId();
        }

        return $id;
    }

    /**
     * Removes the release from disk and database
     *
     * @param int $id The ID of the series
     */
    public function delete($id)
    {
        // this method is constructed so if any part fails,
        // executing this function again will continue the deletion process

        $dc = $this->dc;

        // we can't get around fetching the series data, we need it to delete the directory
        $release_bulk = $this->getById($id);

        $dir = DOCROOT.'foolslide/series/'.$release_bulk->series->id.'/'.$release_bulk->release->id;
        if (file_exists($dir)) {
            Util::delete($dir);
        }

        // delete all the pages related to this chapter from the database
        $dc->qb()
            ->delete($dc->p('pages'))
            ->where('release_id = :release_id')
            ->setParameter(':release_id', $id)
            ->execute();

        // delete the release from the database
        $dc->qb()
            ->delete($dc->p('releases'))
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute();
    }

    /**
     * Returns the content of a release and the parent series
     *
     * @param int $id The ID of a series
     *
     * @return ReleaseBulk The bulk object with the series data object inside
     * @throws ReleaseNotFoundException If the ID doesn't correspond to a release
     */
    public function getById($id)
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('releases'), 'r')
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute()
            ->fetch();

        if (!$result) {
            throw new ReleaseNotFoundException(_i('The release could not be found.'));
        }

        $series_data = $this->series_factory->getById($result['series_id'])->series;

        $release_data = new ReleaseData();
        $release_data->import($result);

        return ReleaseBulk::forge($series_data, $release_data);
    }

    /**
     * Fills the SeriesBulk with the related releases
     *
     * @param SeriesBulk $series_bulk The series bulk to fill
     */
    public function fillSeriesBulk(SeriesBulk $series_bulk)
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('releases'), 'r')
            ->where('series_id = :series_id')
            ->setParameter(':series_id', $series_bulk->series->id)
            ->execute()
            ->fetchAll();

        $release_array = [];

        foreach ($result as $key => $r) {
            $release_data = new ReleaseData();
            $release_data->import($r);
            $release_array[] = $release_data;
            unset($result[$key]);
        }

        $series_bulk->release_array = $release_array;
    }
}