<?php

/**
 * Per-ticket-type identity (colour, badge, wording) shared by the PDF ticket
 * and the gate-pass email so a Performer pass never reads as an Audience one.
 */
class CavemenTicketTier
{
    /**
     * @return array<string,mixed>
     */
    public static function forType($attendanceType, $isDahk = false)
    {
        $key = strtolower(trim((string) $attendanceType));
        if (!$isDahk && $key === 'performer') {
            return self::performer();
        }
        if (!$isDahk && $key === 'audience') {
            return self::audience();
        }

        return self::standard($attendanceType);
    }

    private static function performer()
    {
        return [
            'key' => 'performer',
            'label' => 'Performer',
            'badge' => 'Stage access',
            'admitWord' => 'Stage',
            'mark' => '★',
            'summary' => 'Stage pass — you are on the running order for this session.',
            'lines' => [
                'Call time is 45 minutes before doors for sound check and running order.',
                'One piece, 5 minutes maximum. Bring an offline or printed copy of your text.',
            ],
            'paper' => '#f8f1de',
            'ink' => '#3a1712',
            'accent' => '#c9a962',
            'rule' => '#e2d2ab',
            'onInk' => '#f8f1de',
            'inkSoft' => '#bb9c6d',
            'emailHeader' => '#3f2418',
            'emailAccent' => '#d9b46b',
            'emailBadgeInk' => '#2a1810',
        ];
    }

    private static function audience()
    {
        return [
            'key' => 'audience',
            'label' => 'Audience',
            'badge' => 'Audience seat',
            'admitWord' => 'Seat',
            'mark' => '●',
            'summary' => 'Audience seat — reserved entry for the room, no performance slot.',
            'lines' => [
                'Doors open 30 minutes before the session; seating is first come, first served.',
                'Phones on silent once the mic is live. Snaps encouraged.',
            ],
            'paper' => '#f4efe4',
            'ink' => '#1a2744',
            'accent' => '#5f8d74',
            'rule' => '#d3ddd4',
            'onInk' => '#eef4ef',
            'inkSoft' => '#8fa89a',
            'emailHeader' => '#1e3d2f',
            'emailAccent' => '#8fc2a3',
            'emailBadgeInk' => '#12261c',
        ];
    }

    private static function standard($attendanceType)
    {
        $label = trim((string) $attendanceType);

        return [
            'key' => 'standard',
            'label' => $label !== '' ? $label : 'Admit one',
            'badge' => 'Admit one',
            'admitWord' => '01',
            'mark' => '◆',
            'summary' => '',
            'lines' => [],
            'paper' => '#f4efe4',
            'ink' => '#1a2744',
            'accent' => '#c9a962',
            'rule' => '#d9cdb8',
            'onInk' => '#f4efe4',
            'inkSoft' => '#8a96ab',
            'emailHeader' => '#1e3d2f',
            'emailAccent' => '#e8a090',
            'emailBadgeInk' => '#221007',
        ];
    }
}
