<?php

namespace Foolz\Foolslide\Model;

use Foolz\Foolframe\Model\Config;
use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Model;
use Foolz\Foolframe\Model\Preferences;
use Foolz\Plugin\Hook;
use Foolz\Profiler\Profiler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class PageNotFoundException extends \Exception {}

class PageUploadException extends \Exception {}
class PageUploadNoFileException extends PageUploadException {}
class PageUploadMultipleNotAllowedException extends PageUploadException {}
class PageUploadInvalidException extends PageUploadException {}
class PageUploadInvalidFormatException extends PageUploadException {}

class PageFactory extends Model
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
        $this->series_factory = $context->getService('foolslide.series_factory');
        $this->release_factory = $context->getService('foolslide.release_factory');
    }

    /**
     * @param ReleaseBulk $release_bulk
     * @param UploadedFile[] $files A flat array of UploadedFile objects
     * @throws PageUploadInvalidException
     * @throws PageUploadInvalidFormatException
     * @throws PageUploadNoFileException
     */
    public function addFromFileArray(ReleaseBulk $release_bulk, array $files) {
        $config = Hook::forge('Foolz\Foolslide\Model\PageFactory::upload.config')
            ->setParams([
                'ext_whitelist' => ['jpg', 'jpeg', 'gif', 'png'],
                'mime_whitelist' => ['image/jpeg', 'image/png', 'image/gif']
            ])
            ->execute()
            ->getParams();

        if (!$files)
        {
            throw new PageUploadNoFileException(_i('You must upload an image or your image was too large.'));
        }

        foreach ($files as $file) {
            if (!$file->isValid()) {

                if ($file->getError() === UPLOAD_ERR_INI_SIZE) {
                    throw new PageUploadInvalidException(
                        _i('The server is misconfigured: the Foolslide upload size should be lower than PHP\'s upload limit.'));
                }

                if ($file->getError() === UPLOAD_ERR_PARTIAL) {
                    throw new PageUploadInvalidException(_i('You uploaded the file partially.'));
                }

                if ($file->getError() === UPLOAD_ERR_CANT_WRITE) {
                    throw new PageUploadInvalidException(_i('The image couldn\'t be saved on the disk.'));
                }

                if ($file->getError() === UPLOAD_ERR_EXTENSION) {
                    throw new PageUploadInvalidException(_i('A PHP extension broke and made processing the image impossible.'));
                }

                throw new PageUploadInvalidException(_i('Unexpected upload error.'));
            }

            if (mb_strlen($file->getFilename(), 'utf-8') > 64) {
                throw new PageUploadInvalidException(_i('You uploaded a file with a too long filename.'));
            }

            if (!in_array(strtolower($file->getClientOriginalExtension()), $config['ext_whitelist'])) {
                throw new PageUploadInvalidException(_i('You uploaded a file with an invalid extension.'));
            }

            if (!in_array(strtolower($file->getMimeType()), $config['mime_whitelist'])) {
                throw new PageUploadInvalidException(_i('You uploaded a file with an invalid mime type.'));
            }

            /*
             * Disabled max size
             *
            if ($file->getClientSize() > $max_size && !$this->getAuth()->hasAccess('media.limitless_media')) {
                throw new PageUploadInvalidException(
                    _i('You uploaded a too big file. The maxmimum allowed filesize is %s',
                        $radix->getValue('max_image_size_kilobytes')));
            }
            */
        }

        $dc = $this->dc;

        foreach ($files as $file) {
            $page_data = new PageData();

            $page_data->release_id = $release_bulk->release->id;

            $page_data->filename = $file->getClientOriginalName();
            $page_data->extension = $file->getClientOriginalExtension();
            $page_data->filesize = $file->getSize();

            $imagesize = getimagesize($file->getPathname());

            if (!$imagesize) {
                throw new PageUploadInvalidFormatException(_i('The file you uploaded is not allowed.'));
            }

            $page_data->width = $imagesize[0];
            $page_data->height = $imagesize[1];

            $page_data->hash = sha1_file($file->getPathname());

            $dc->getConnection()
                ->insert($dc->p('pages'), $page_data->export());
            $id = $dc->getConnection()->lastInsertId();

            $dir = DOCROOT.'foolslide/series/'.$release_bulk->series->id.'/'.$release_bulk->release->id.'/';
            if (!file_exists($dir)) {
                mkdir($dir, 0655, true);
            }

            $file->move($dir, $id.'.'.$page_data->extension);
        }
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

        // we can't get around fetching the series and release data, we need it to delete the file
        $page_bulk = $this->getById($id);

        $file = DOCROOT.'foolslide/series/'.$page_bulk->series->id.'/'.$page_bulk->release->id.'/'.$page_bulk->page->id
            .$page_bulk->page->extension;

        if (file_exists($file)) {
            unlink($file);
        }

        // delete the page from the database
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
     * @return PageBulk The bulk object with the series data object inside
     * @throws PageNotFoundException If the ID doesn't correspond to a release
     */
    public function getById($id)
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('pages'), 'p')
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute()
            ->fetch();

        if (!$result) {
            throw new PageNotFoundException(_i('The page could not be found.'));
        }

        $release_bulk = $this->release_factory->getById($result['release_id']);

        $page_data = new PageData();
        $page_data->import($result);

        return PageBulk::forge($release_bulk->series, $release_bulk->release, $page_data);
    }

    /**
     * Fills the ReleaseBulk with the related pages
     *
     * @param ReleaseBulk $release_bulk The release bulk to fill
     */
    public function fillReleaseBulk(ReleaseBulk $release_bulk)
    {
        $dc = $this->dc;

        $result = $dc->qb()
            ->select('*')
            ->from($dc->p('pages'), 'r')
            ->where('release_id = :release_id')
            ->setParameter(':release_id', $release_bulk->release->id)
            ->execute()
            ->fetchAll();

        $page_array = [];

        foreach ($result as $key => $r) {
            $page_data = new PageData();
            $page_data->import($r);
            $page_array[] = $page_data;
            unset($result[$key]);
        }

        $release_bulk->page_array = $page_array;
    }
}