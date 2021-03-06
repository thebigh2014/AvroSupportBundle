<?php

namespace Avro\SupportBundle\Manager\Doctrine\Document;

use Avro\SupportBundle\Model\QuestionInterface;
use Avro\SupportBundle\Model\QuestionManagerInterface;
use Avro\SupportBundle\Manager\Doctrine\BaseManager;
use Avro\SupportBundle\Event\QuestionEvent;
use Avro\SupportBundle\Event\AnswerEvent;

use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Knp\Component\Pager\Paginator;


/*
 * Managing class for Question entity
 *
 * @author Joris de Wit <joris.w.dewit@gmail.com>
 */
class QuestionManager extends BaseManager
{
    public function __construct(ObjectManager $om, Paginator $paginator, EventDispatcherInterface $dispatcher, $class)
    {
        parent::__construct(
            $om,
            $paginator,
            $dispatcher,
            $class,
            'avro_support',
            'question',
            'Avro\SupportBundle\Event\QuestionEvent'
        );
    }

    /**
     * Create a new answer
     *
     * @return Answer
     */
    public function createAnswer()
    {
        $answerClass = 'Avro\SupportBundle\Document\Answer';

        return new $answerClass();
    }

    /**
     * Add a new answer
     *
     * @param Question $question
     * @param Answer $answer
     * @return Question
     */
    public function addAnswer($question, $answer)
    {
        $this->dispatcher->dispatch('avro_support.answer.add', new AnswerEvent($answer, $question));

        $question->addAnswer($answer);
        $this->update($question);

        $this->dispatcher->dispatch('avro_support.answer.added', new AnswerEvent($answer, $question));

        return $question;
    }

    /**
     * Remove an answer
     *
     * @param QuestionInterface $question
     * @param string $answerId
     */
    public function removeAnswer($question, $answerId)
    {
        foreach($question->getAnswers() as $answer) {
            if ($answer->getId() == $answerId) {
                $question->removeAnswer($answer);
            }
        }
    }

    /**
     * getFaqQuestionsQuery
     *
     * @return Query
     */
    public function getFaqQuestionsQuery()
    {
        $qb = $this->getQueryBuilder();

        $qb->field('isFaq')->equals(true);
        $qb->sort('views', 'DESC');

        return $qb->getQuery();
    }

    /**
     * Get a users questions
     *
     * @param string $userId
     * @param string $constraint
     * @return Query
     */
    public function getUsersQuestionsQuery($userId, $constraint = false)
    {
        $qb = $this->getQueryBuilder();
        $qb->field('authorId')->equals($userId);
        $qb->sort('createdAt', 'DESC');

        switch ($constraint) {
            case 'Open':
                $qb->field('isSolved')->notEqual(true);
            break;
            case 'Solved':
                $qb->field('isSolved')->equals(true);
            break;
        }

        return $qb->getQuery();
    }

    /**
     * Get all questions (for admin)
     *
     * @param string $constraint
     * @return Query
     */
    public function getAllQuestionsQuery($constraint = false)
    {
        $qb = $this->getQueryBuilder();
        $qb->sort('createdAt', 'DESC');

        switch ($constraint) {
            case 'Open':
                $qb->field('isSolved')->notEqual(true);
            break;
            case 'Solved':
                $qb->field('isSolved')->equals(true);
            break;
        }

        return $qb->getQuery();
    }

    /**
     * Should be using solr or something but whatever
     * @param string search query
     * @param string author ID
     * @return Query
     */
    public function getSearchQuery($query, $authorId)
    {
        $qb = $this->getQueryBuilder();
        $qb->sort('views', 'DESC');

        $words = str_replace(array(',', '.'), '', $query);
        $words = explode(' ', $query);

        $qb->addAnd($qb->expr()
            ->addOr($qb->expr()->field('isPublic')->equals(true))
            ->addOr($qb->expr()->field('isFaq')->equals(true))
            ->addOr($qb->expr()->field('authorId')->equals($authorId))
        );

        foreach($words as $word) {
            $qb->addAnd($qb->expr()
                ->addOr($qb->expr()->field('title')->equals(new \MongoRegex('/.*'.$word.'.*/i')))
                ->addOr($qb->expr()->field('body')->equals(new \MongoRegex('/.*'.$word.'.*/i')))
            );

        }

        return $qb->getQuery();
    }

    /**
     * Get admin search query
     * @param string search query
     * @return Query
     */
    public function getAdminSearchQuery($query)
    {
        $qb = $this->getQueryBuilder();
        $qb->sort('views', 'DESC');

        $words = str_replace(array(',', '.'), '', $query);
        $words = explode(' ', $query);

        foreach($words as $word) {
            $qb->addAnd($qb->expr()
                ->addOr($qb->expr()->field('title')->equals(new \MongoRegex('/.*'.$word.'.*/i')))
                ->addOr($qb->expr()->field('body')->equals(new \MongoRegex('/.*'.$word.'.*/i')))
            );

        }

        return $qb->getQuery();
    }

    /*
     * Search by category
     *
     * @param string $categoryId
     */
    public function searchByCategory($categoryId)
    {
        $qb = $this->getQueryBuilder();
        $qb->field('isPublic')->equals(true);
        $qb->field('categorys')->equals($categoryId);
        $qb->sort('views', 'DESC');

        $query = $qb->getQuery();

        return $this->paginate($query);
    }

    /*
     * Search by user
     *
     * @param string $id
     */
    public function searchByUser($id)
    {
        $qb = $this->getQueryBuilder();
        $qb->field('isPublic')->equals(true);
        $qb->field('authorId')->equals($id);
        $qb->sort('views', 'DESC');

        $query = $qb->getQuery();

        return $this->paginate($query);
    }
}

