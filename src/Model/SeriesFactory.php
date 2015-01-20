<?php

namespace Foolz\Foolslide\Model;

use Foolz\Foolframe\Model\Config;
use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Model;
use Foolz\Foolframe\Model\Preferences;
use Foolz\Foolframe\Model\Util;
use Foolz\Profiler\Profiler;
use Symfony\Component\Validator\Constraints as Assert;

class SeriesNotFoundException extends \Exception {}

class SeriesFactory extends Model
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
     * @var ReleaseFactory
     */
    protected $release_factory;

    public function __construct(\Foolz\Foolframe\Model\Context $context)
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->preferences = $context->getService('preferences');
        $this->config = $context->getService('config');
        $this->profiler = $context->getService('profiler');
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
                        ->from($dc->p('series'), 's')
                        ->where('id = :id')
                        ->setParameter(':id', $input['id'])
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
            'title' => [
                'database' => true,
                'type' => 'input',
                'label' => _i('Title'),
                'help' => _i('Insert the title of the series.'),
                'placeholder' => _i('Required'),
                'class' => 'span3',
                'validation' => [new Assert\NotBlank(), new Assert\Length(['max' => 256])]
            ],
            'synopsis' => [
                'database' => true,
                'boards_preferences' => true,
                'type' => 'textarea',
                'label' => _i('Synopsis'),
                'help' => _i('Insert the synopsis of the series.'),
                'class' => 'span6',
                'placeholder' => _i('Insert the synopsis of the series'),
                 'validation' => [new Assert\Length(['max' => 65535])]
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
     * Updates or adds a series in the database
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
                ->update($dc->p('series'), 's');

            foreach ($data as $k => $i) {
                $update->set($k, $dc->getConnection()->quote($i));
            }

            $update->where('id = :id')
                ->setParameter(':id', $data['id'])
                ->execute();

            $id = $data['id'];

        } else {
            // insert new series
            $dc->getConnection()
                ->insert($dc->p('series'), $data);

            $id = $dc->getConnection()->lastInsertId();
        }

        return $id;
    }

    /**
     * Removes the series from disk and database
     *
     * @param int $id The ID of the series
     */
    public function delete($id)
    {
        // this method is constructed so if any part fails,
        // executing this function again will continue the deletion process

        $dc = $this->dc;

        // delete the entire directory of the series
        $dir = DOCROOT.'foolslide/series/'.$id;
        if (file_exists($dir)) {
            Util::delete($dir);
        }

        // delete all the pages related to the series from the database
        $dc->qb()
            ->delete($dc->p('pages'), 'p')
            ->join('p', $dc->p('releases'), 'r', 'p.release_id = r.id')
            ->where('p.series_id = :series_id')
            ->setParameter(':series_id', $id)
            ->execute();

        // delete all the releases related to the series from the database
        $dc->qb()
            ->delete($dc->p('releases'))
            ->where('series_id = :series_id')
            ->setParameter(':series_id', $id)
            ->execute();

        // delete the series from the database
        $dc->qb()
            ->delete($dc->p('series'))
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute();
    }

    /**
     * Returns the content of a series
     *
     * @param int $id The ID of a series
     *
     * @return SeriesBulk The bulk object with the series data object inside
     * @throws SeriesNotFoundException If the ID doesn't correspond to a
     */
    public function getById($id)
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('series'), 's')
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute()
            ->fetch();

        if (!$result) {
            throw new SeriesNotFoundException(_i('The series could not be found.'));
        }

        $data = new SeriesData();
        $data->import($result);

        return SeriesBulk::forge($data);
    }

    /**
     * Returns a limited about of series
     *
     * @param int $page The page to start from, starts from 1
     * @param int $per_page How many series to fetch
     * @param string $order_by The column to order by
     * @param string $order_direction The direction to order with, 'asc' or 'desc'
     *
     * @return SeriesBulk
     */
    public function getPaged($page = 1, $per_page = 100, $order_by = 'title', $order_direction = 'asc')
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('series'), 's')
            ->orderBy($order_by, $order_direction)
            ->setMaxResults($per_page)
            ->setFirstResult($per_page * $page - $per_page)
            ->execute()
            ->fetchAll();

        if (!count($result)) {
            return [];
        }

        $series_array = [];
        $data = new SeriesData();

        foreach ($result as $r) {
            $data->import($r);
            $series_array[] = SeriesBulk::forge($data);
        }

        return $series_array;
    }
}