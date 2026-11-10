<?php

namespace Omnipulse;

use Omnipulse\Model\Place;
use Omnipulse\Model\Review;

/** Reviews of the establishment, as the platform gives them: in its order, as many as it allows. */
interface ReviewsInterface extends TermsInterface
{
    /**
     * @param int         $limit    how many at most: the platform may give fewer (Terms::$maxReviews)
     * @param string|null $language the reviews' language, where the platform filters or translates
     *
     * @return list<Review>
     */
    public function reviews(Place $place, int $limit = 5, ?string $language = null): array;
}
