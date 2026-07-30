<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\InvalidRequestException;
use Leantime\Plugins\APIData\Model\RequestParameters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestParameters::class)]
final class RequestParametersTest extends TestCase
{
    public function testAnEmptyRequestFallsBackToTheDocumentedDefaults(): void
    {
        $parameters = RequestParameters::fromInput([]);

        $this->assertSame(0, $parameters->start);
        $this->assertSame(RequestParameters::DEFAULT_LIMIT, $parameters->limit);
        $this->assertNull($parameters->modifiedAfter);
        $this->assertNull($parameters->ids);
        $this->assertNull($parameters->projectIds);
    }

    /**
     * The endpoints are documented as GET with query parameters, so every value
     * can arrive as a string.
     */
    public function testNumericStringsFromAQueryStringBecomeIntegers(): void
    {
        $parameters = RequestParameters::fromInput([
            'start' => '25',
            'limit' => '50',
            'modifiedAfter' => '1761051213',
        ]);

        $this->assertSame(25, $parameters->start);
        $this->assertSame(50, $parameters->limit);
        $this->assertSame(1761051213, $parameters->modifiedAfter);
    }

    /**
     * Laravel ignores a negative limit, which drops the LIMIT clause and returns
     * the entire table.
     */
    public function testANegativeLimitIsRejectedRatherThanReturningEveryRow(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['limit' => '-1']);
    }

    public function testAZeroLimitIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['limit' => 0]);
    }

    /**
     * The effective limit is echoed back in the response parameters, so a capped
     * request is visible to the caller rather than silently different.
     */
    public function testALimitAboveTheMaximumIsCapped(): void
    {
        $parameters = RequestParameters::fromInput(['limit' => 5000]);

        $this->assertSame(RequestParameters::MAX_LIMIT, $parameters->limit);
        $this->assertSame(RequestParameters::MAX_LIMIT, $parameters->toArray()['limit']);
    }

    public function testANegativeStartIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['start' => -5]);
    }

    /**
     * A plain (int) cast turns "abc" into 0, so a typo used to read as "from the
     * beginning of time" and pull the whole history.
     */
    public function testANonNumericTimestampIsRejectedInsteadOfCastingToZero(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['modifiedAfter' => 'yesterday']);
    }

    public function testAFractionalTimestampIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['modifiedAfter' => '1761051213.5']);
    }

    public function testAnEmptyTimestampIsTreatedAsAbsent(): void
    {
        $parameters = RequestParameters::fromInput(['modifiedAfter' => '']);

        $this->assertNull($parameters->modifiedAfter);
    }

    /**
     * `?ids=1,2,3` is the natural way to send a list as a query parameter. It
     * used to reach whereIn() as a string, whose nested-array guard calls count()
     * on it and fails the request.
     */
    public function testACommaSeparatedListIsAcceptedForIds(): void
    {
        $parameters = RequestParameters::fromInput(['ids' => '1, 2,3', 'projectIds' => '12,13']);

        $this->assertSame([1, 2, 3], $parameters->ids);
        $this->assertSame([12, 13], $parameters->projectIds);
    }

    public function testARealArrayIsAcceptedForIds(): void
    {
        $parameters = RequestParameters::fromInput(['ids' => [1, '2', 3]]);

        $this->assertSame([1, 2, 3], $parameters->ids);
    }

    public function testANonNumericIdIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['ids' => '1,x,3']);
    }

    public function testANestedArrayOfIdsIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        RequestParameters::fromInput(['ids' => [[1, 2]]]);
    }

    /**
     * An empty string means the parameter was never really sent, so it must not
     * turn into a filter that matches nothing.
     */
    public function testAnEmptyIdsStringIsTreatedAsAbsent(): void
    {
        $parameters = RequestParameters::fromInput(['ids' => '']);

        $this->assertNull($parameters->ids);
    }

    /**
     * An explicitly empty array is a list with nothing in it, which keeps the
     * existing behaviour of matching no rows.
     */
    public function testAnEmptyIdsArrayStaysAnEmptyFilter(): void
    {
        $parameters = RequestParameters::fromInput(['ids' => []]);

        $this->assertSame([], $parameters->ids);
    }
}
