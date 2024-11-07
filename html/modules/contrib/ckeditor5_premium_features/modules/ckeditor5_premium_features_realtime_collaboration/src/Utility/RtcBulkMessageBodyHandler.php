<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\BulkMessageBodyHandlerInterface;

/**
 * Class responsible for preparing body for bulk message.
 */
class RtcBulkMessageBodyHandler implements BulkMessageBodyHandlerInterface {

  /**
   * Constructor.
   *
   * @param BulkNotificationIntegrator $rtcBulkNotificationIntegrator
   *   Bulk notifications integrator for the RTC module.
   */
  public function __construct(protected BulkNotificationIntegrator $rtcBulkNotificationIntegrator) {
  }

  /**
   * {@inheritDoc}
   */
  public function prepareBody(Message $message, NotificationMessageFactoryInterface $messageFactory): array {
    return $this->rtcBulkNotificationIntegrator->handleBulkNotification($message, $messageFactory);
  }

}
