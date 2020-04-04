<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Configuration\Configuration;
use App\Services\Sync\JobStatus\ProgressInformation;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Transaction;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionGroup;
use GrumpyDictator\FFIIIApiSupport\Request\PostTransactionRequest;
use GrumpyDictator\FFIIIApiSupport\Response\PostTransactionResponse;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;

/**
 * Class SendTransactions.
 */
class SendTransactions
{
    use ProgressInformation;

    /** @var Configuration */
    private $configuration;

    /**
     * @param array $transactions
     *
     * @return array
     */
    public function send(array $transactions): array
    {
        $uri   = (string) config('ynab.uri');
        $token = (string) config('ynab.access_token');
        foreach ($transactions as $index => $transaction) {
            app('log')->debug(sprintf('Trying to send transaction #%d', $index), $transaction);
            $this->sendTransaction($uri, $token, $index, $transaction);
        }

        return [];
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $uri
     * @param string $token
     * @param int    $index
     * @param array  $transaction
     *
     * @return array
     */
    private function sendTransaction(string $uri, string $token, int $index, array $transaction): array
    {
        $request = new PostTransactionRequest($uri, $token);
        $request->setBody($transaction);
        try {
            /** @var PostTransactionResponse $response */
            $response = $request->post();
        } catch (ApiHttpException $e) {
            app('log')->error($e->getMessage());
            $this->addError($index, $e->getMessage());

            return [];
        }
        if ($response instanceof ValidationErrorResponse) {
            /** ValidationErrorResponse $error */
            foreach ($response->errors->getMessages() as $key => $errors) {
                foreach ($errors as $error) {
                    // +1 so the line numbers match.
                    $this->addError($index + 1, $error);
                    app('log')->error(sprintf('Could not create transaction: %s', $error), $transaction);
                }
            }

            return [];
        }
        /** @var TransactionGroup|null $group */
        $group = $response->getTransactionGroup();
        if (null === $group) {
            $this->addError($index + 1, 'Group is unexpectedly NULL.');

            return [];
        }
        $groupId  = $group->id;
        $uri      = (string) config('ynab.uri');
        $groupUri = (string) sprintf('%s/transactions/show/%d', $uri, $groupId);

        /** @var Transaction $tr */
        foreach ($group->transactions as $tr) {
            $this->addMessage(
                $index + 1,
                sprintf(
                    'Created transaction #%d: <a href="%s">%s</a> (%s %s)', $groupId, $groupUri, $tr->description, $tr->currencyCode,
                    round((float) $tr->amount, 2)
                )
            );
        }

        return [];
    }
}
