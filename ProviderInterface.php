<?php

namespace Omnipulse;

/**
 * One outside platform that knows something of a site or a project,
 * configured: a search engine or an analytics tool (its audience), a
 * review platform (what customers say of an establishment), a funding
 * platform (who funds the project). Its Capabilities say what it can be
 * asked - SearchPerformanceInterface, AudienceInterface; RatingInterface,
 * ReviewsInterface, ReplyInterface, InviteInterface, WriteUrlInterface;
 * ProfileInterface, SponsorsInterface, TiersInterface - and whose terms
 * apply.
 */
interface ProviderInterface
{
    /** The factory's name: "search-console", "matomo", "google-reviews", "trustpilot", "liberapay"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
