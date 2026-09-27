<?php
declare(strict_types=1);

namespace Belis\Payments;

/**
 * The only way the shop talks to a payment provider (CTL-PAY-001, CTL-PAY-002). Card and mobile money details are
 * entered on the provider's own page and never reach this application.
 */
interface PaymentAdapter
{
    /**
     * Start a payment. Returns the provider page the customer is sent to.
     *
     * @return string absolute https URL (or a local URL for the mock adapter)
     * @throws \RuntimeException when the provider cannot be reached or refuses
     */
    public function initialize(string $email, int $amountPesewas, string $reference, string $callbackUrl): string;

    /**
     * Ask the provider what happened to a payment. This is the only source of truth for an order becoming paid.
     *
     * @return array{status:string,amount:int,currency:string,reference:string} status is success, failed or pending
     * @throws \RuntimeException when the provider cannot be reached
     */
    public function verify(string $reference): array;

    /**
     * Ask the provider to refund part or all of a paid transaction, back to the original payment only.
     *
     * @return array{status:string} pending or processed (a refusal throws)
     * @throws \RuntimeException when the provider cannot be reached or refuses
     */
    public function refund(string $paymentReference, int $amountPesewas, string $note): array;

    /**
     * What the provider says about the refunds of one transaction. The only source of truth for refund progress.
     *
     * @return list<array{amount:int,status:string}> status is pending, processed or failed
     * @throws \RuntimeException when the provider cannot be reached
     */
    public function refunds(string $paymentReference): array;
}
