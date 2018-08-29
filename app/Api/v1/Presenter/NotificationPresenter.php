<?php

namespace App\Api\V1\Presenter;
use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/28
 * Time: 上午7:48
 */
class NotificationPresenter
{
    public function convertStrTypeToInt($type)
    {
        switch ($type)
        {
            case 'post-like':
                return 1;
                break;
            case 'post-reward':
                return 2;
                break;
            case 'post-mark':
                return 3;
                break;
            case 'post-comment':
                return 4;
                break;
            case 'post-reply':
                return 5;
                break;
            case 'image-like':
                return 6;
                break;
            case 'image-reward':
                return 7;
                break;
            case 'image-mark':
                return 8;
                break;
            case 'image-comment':
                return 9;
                break;
            case 'image-reply':
                return 10;
                break;
            case 'score-like':
                return 11;
                break;
            case 'score-reward':
                return 12;
                break;
            case 'score-mark':
                return 13;
                break;
            case 'score-comment':
                return 14;
                break;
            case 'score-reply':
                return 15;
                break;
            case 'video-comment':
                return 16;
                break;
            case 'video-reply':
                return 17;
                break;
            case 'post-comment-like':
                return 18;
                break;
            case 'post-reply-like':
                return 19;
                break;
            case 'image-comment-like':
                return 20;
                break;
            case 'image-reply-like':
                return 21;
                break;
            case 'score-comment-like':
                return 22;
                break;
            case 'score-reply-like':
                return 23;
                break;
            case 'video-comment-like':
                return 24;
                break;
            case 'video-reply-like':
                return 25;
                break;
            case 'question-follow':
                return 26;
                break;
            case 'question-comment':
                return 27;
                break;
            case 'question-comment-like':
                return 28;
                break;
            case 'question-reply':
                return 29;
                break;
            case 'question-reply-like':
                return 30;
                break;
            case 'question-answer':
                return 31;
                break;
            case 'answer-vote':
                return 32;
                break;
            case 'answer-like':
                return 33;
                break;
            case 'answer-reward':
                return 34;
                break;
            case 'answer-mark':
                return 35;
                break;
            case 'answer-comment':
                return 36;
                break;
            case 'answer-reply':
                return 37;
                break;
            case 'answer-comment-like':
                return 38;
                break;
            case 'answer-reply-like':
                return 39;
                break;
            default:
                return 0;
                break;
        }
    }

    public function computeNotificationLink($type, $modalId, $commentId = 0, $replyId = 0)
    {
        switch ($type)
        {
            case 0:
                return '';
                break;
            case 1:
                return '/post/' . $modalId;
                break;
            case 2:
                return '/post/' . $modalId;
                break;
            case 3:
                return '/post/' . $modalId;
                break;
            case 4:
                return '/post/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 5:
                return '/post/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 6:
                return '/pins/' . $modalId;
                break;
            case 7:
                return '/pins/' . $modalId;
                break;
            case 8:
                return '/pins/' . $modalId;
                break;
            case 9:
                return '/pins/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 10:
                return '/pins/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 11:
                return '/review/' . $modalId;
                break;
            case 12:
                return '/review/' . $modalId;
                break;
            case 13:
                return '/review/' . $modalId;
                break;
            case 14:
                return '/review/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 15:
                return '/review/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 16:
                return '/video/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 17:
                return '/video/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 18:
                return '/post/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 19:
                return '/post/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 20:
                return '/pins/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 21:
                return '/pins/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 22:
                return '/review/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 23:
                return '/review/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 24:
                return '/video/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 25:
                return '/video/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 26:
                return '/qaq/' . $modalId;
                break;
            case 27:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 28:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 29:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 30:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 31:
                return '/soga/' . $modalId;
                break;
            case 32:
                return '/soga/' . $modalId;
                break;
            case 33:
                return '/soga/' . $modalId;
                break;
            case 34:
                return '/soga/' . $modalId;
                break;
            case 35:
                return '/soga/' . $modalId;
                break;
            case 36:
                return '/soga/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 37:
                return '/soga/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 38:
                return '/soga/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 39:
                return '/soga/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            default:
                return '';
                break;
        }
    }

    public function computeNotificationMessage($type)
    {
        switch ($type)
        {
            case 0:
                return '';
                break;
            case 1:
                return '${user}喜欢了你的帖子${title}';
                break;
            case 2:
                return '${user}打赏了你的帖子${title}';
                break;
            case 3:
                return '${user}收藏了你的帖子${title}';
                break;
            case 4:
                return '${user}评论了你的帖子${title}';
                break;
            case 5:
                return '${user}回复了你在的帖子${title}下的评论';
                break;
            case 6:
                return '${user}喜欢了你的图片${title}';
                break;
            case 7:
                return '${user}打赏了你的图片${title}';
                break;
            case 8:
                return '${user}收藏了你的图片${title}';
                break;
            case 9:
                return '${user}评论了你的图片${title}';
                break;
            case 10:
                return '${user}回复了你在的图片${title}下的评论';
                break;
            case 11:
                return '${user}喜欢了你的漫评${title}';
                break;
            case 12:
                return '${user}打赏了你的漫评${title}';
                break;
            case 13:
                return '${user}收藏了你的漫评${title}';
                break;
            case 14:
                return '${user}评论了你的漫评${title}';
                break;
            case 15:
                return '${user}回复了你在的漫评${title}下的评论';
                break;
            case 16:
                return '${user}评论了你的视频${title}';
                break;
            case 17:
                return '${user}回复了你在的视频${title}下的评论';
                break;
            case 18:
                return '${user}赞了你在的帖子${title}下的评论';
                break;
            case 19:
                return '${user}赞了你在的帖子${title}下的回复';
                break;
            case 20:
                return '${user}赞了你在的图片${title}下的评论';
                break;
            case 21:
                return '${user}赞了你在的图片${title}下的回复';
                break;
            case 22:
                return '${user}赞了你在的评分${title}下的评论';
                break;
            case 23:
                return '${user}赞了你在的评分${title}下的回复';
                break;
            case 24:
                return '${user}赞了你在的视频${title}下的评论';
                break;
            case 25:
                return '${user}赞了你在的视频${title}下的回复';
                break;
            case 26:
                return '${user}关注了你提的问题${title}';
                break;
            case 27:
                return '${user}评论了你提的问题${title}';
                break;
            case 28:
                return '${user}赞了你在问题${title}下的评论';
                break;
            case 29:
                return '${user}回复了你在问题${title}下的评论';
                break;
            case 30:
                return '${user}赞了你在问题${title}下的回复';
                break;
            case 31:
                return '${user}回答了你的问题${title}';
                break;
            case 32:
                return '${user}赞同了你在问题${title}下的回答';
                break;
            case 33:
                return '${user}喜欢了你在问题${title}下的回答';
                break;
            case 34:
                return '${user}打赏了你在问题${title}下的回答';
                break;
            case 35:
                return '${user}收藏了你在问题${title}下的回答';
                break;
            case 36:
                return '${user}评论了你在问题${title}下的回答';
                break;
            case 37:
                return '${user}回复了你在问题${title}下的评论';
                break;
            case 38:
                return '${user}赞了你在问题${title}下的评论';
                break;
            case 39:
                return '${user}赞了你在问题${title}下的回复';
                break;
            default:
                return '';
                break;
        }
    }

    public function computeNotificationRepository($type)
    {
        switch ($type)
        {
            case 0:
                return null;
                break;
            case 1:
                return new PostRepository();
                break;
            case 2:
                return new PostRepository();
                break;
            case 3:
                return new PostRepository();
                break;
            case 4:
                return new PostRepository();
                break;
            case 5:
                return new PostRepository();
                break;
            case 6:
                return new ImageRepository();
                break;
            case 7:
                return new ImageRepository();
                break;
            case 8:
                return new ImageRepository();
                break;
            case 9:
                return new ImageRepository();
                break;
            case 10:
                return new ImageRepository();
                break;
            case 11:
                return new ScoreRepository();
                break;
            case 12:
                return new ScoreRepository();
                break;
            case 13:
                return new ScoreRepository();
                break;
            case 14:
                return new ScoreRepository();
                break;
            case 15:
                return new ScoreRepository();
                break;
            case 16:
                return new VideoRepository();
                break;
            case 17:
                return new VideoRepository();
                break;
            case 18:
                return new PostRepository();
                break;
            case 19:
                return new PostRepository();
                break;
            case 20:
                return new ImageRepository();
                break;
            case 21:
                return new ImageRepository();
                break;
            case 22:
                return new ScoreRepository();
                break;
            case 23:
                return new ScoreRepository();
                break;
            case 24:
                return new VideoRepository();
                break;
            case 25:
                return new VideoRepository();
                break;
            case 26:
                return new QuestionRepository();
                break;
            case 27:
                return new QuestionRepository();
                break;
            case 28:
                return new QuestionRepository();
                break;
            case 29:
                return new QuestionRepository();
                break;
            case 30:
                return new QuestionRepository();
                break;
            case 31:
                return new AnswerRepository();
                break;
            case 32:
                return new AnswerRepository();
                break;
            case 33:
                return new AnswerRepository();
                break;
            case 34:
                return new AnswerRepository();
                break;
            case 35:
                return new AnswerRepository();
                break;
            case 36:
                return new AnswerRepository();
                break;
            case 37:
                return new AnswerRepository();
                break;
            case 38:
                return new AnswerRepository();
                break;
            case 39:
                return new AnswerRepository();
                break;
            default:
                return null;
                break;
        }
    }

    public function computeNotificationMessageTitle($model)
    {
        if (isset($model['title']))
        {
            return $model['title'];
        }

        if (isset($model['name']))
        {
            return $model['name'];
        }

        if (isset($model['nickname']))
        {
            return $model['nickname'];
        }

        if (isset($model['intro']))
        {
            return $model['intro'];
        }

        return '';
    }

    public function convertModel($model, $type)
    {
        if (isset($model['question_id']))
        {
            $questionRepository = new QuestionRepository();
            return $questionRepository->item($model['question_id']);
        }

        return $model;
    }
}