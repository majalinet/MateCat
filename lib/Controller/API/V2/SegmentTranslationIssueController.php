<?php

namespace API\V2;

use API\App\AbstractStatefulKleinController;
use API\V2\Json\SegmentTranslationIssue as JsonFormatter;
use API\V2\Validators\ChunkPasswordValidator;
use Database;
use Features\ReviewExtended\ReviewUtils;
use Features\ReviewExtended\TranslationIssueModel;
use Features\SecondPassReview;
use LQA\EntryDao as EntryDao;
use LQA\EntryStruct;
use RevisionFactory;

class SegmentTranslationIssueController extends AbstractStatefulKleinController {

    /**
     * @var RevisionFactory
     */
    protected $revisionFactory;

    /**
     * @var Validators\SegmentTranslationIssue
     */
    private $validator;
    private $issue;

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    public function index() {
        $result = EntryDao::findAllByTranslationVersion(
                $this->validator->translation->id_segment,
                $this->validator->translation->id_job,
                $this->getVersionNumber()
        );

        $json     = new JsonFormatter();
        $rendered = $json->render( $result );

        $this->response->json( [ 'issues' => $rendered ] );
    }

    public function create() {
        $data = [
                'id_segment'          => $this->request->id_segment,
                'id_job'              => $this->request->id_job,
                'id_category'         => $this->request->id_category,
                'severity'            => $this->request->severity,
                'translation_version' => $this->validator->translation->version_number,
                'target_text'         => $this->request->target_text,
                'start_node'          => $this->request->start_node,
                'start_offset'        => $this->request->start_offset,
                'end_node'            => $this->request->end_node,
                'end_offset'          => $this->request->end_offset,
                'is_full_segment'     => false,
                'comment'             => $this->request->comment,
                'uid'                 => $this->user->uid,
                'source_page'         => ReviewUtils::revisionNumberToSourcePage( $this->request->revision_number ),
        ];

        Database::obtain()->begin();

        //TODO refactory validation systems and check if is needed to initialize  EntryStruct twice, here and in \Features\ReviewExtended\TranslationIssueModel line 84

        $struct = new EntryStruct( $data );

        $model = $this->_getSegmentTranslationIssueModel(
                $this->request->id_job,
                $this->request->password,
                $struct
        );

        if ( $this->request->diff ) {
            $model->setDiff( $this->request->diff );
        }

        $struct = $model->save();

        Database::obtain()->commit();

        $json     = new JsonFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    public function update() {
        $issue = null;

        $postParams = $this->request->paramsPost();

        if ( $postParams[ 'rebutted_at' ] == null ) {
            $entryDao = new EntryDao( Database::obtain()->getConnection() );
            $issue    = $entryDao->updateRebutted(
                    $this->validator->issue->id, false
            );
        }

        $json     = new JsonFormatter();
        $rendered = $json->renderItem( $issue );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    public function delete() {

        Database::obtain()->begin();
        $model = $this->_getSegmentTranslationIssueModel(
                $this->request->id_job,
                $this->validator->getChunkReview()->password,
                $this->validator->issue
        );
        $model->delete();
        Database::obtain()->commit();

        $this->response->code( 200 );
    }

    /**
     * @param $id_job
     * @param $password
     * @param $issue
     *
     * @return TranslationIssueModel|SecondPassReview\TranslationIssueModel
     */
    protected function _getSegmentTranslationIssueModel( $id_job, $password, $issue ) {
        return $this->revisionFactory->getTranslationIssueModel( $id_job, $password, $issue );
    }

    protected function afterConstruct() {

        $jobValidator = new ChunkPasswordValidator( $this );
        $jobValidator->onSuccess( function () use ( $jobValidator ) {

            $this->project         = $jobValidator->getChunk()->getProject();
            $this->revisionFactory = RevisionFactory::initFromProject( $this->project );

            //enable dynamic loading ( Factory ) by callback hook on revision features
            $this->validator = $this->revisionFactory->getTranslationIssuesValidator( $this->request );

            $this->validator->validate();

        } );
        $this->appendValidator( $jobValidator );


    }

    private function getVersionNumber() {
        if ( null !== $this->request->param( 'version_number' ) ) {
            return $this->request->param( 'version_number' );
        } else {
            return $this->validator->translation->version_number;
        }
    }

    private function findCategories() {
        $categories = $this->validator->translation
                ->getJob()->getProject()
                ->getLqaModel()->getCategories();

        return $categories;
    }

}