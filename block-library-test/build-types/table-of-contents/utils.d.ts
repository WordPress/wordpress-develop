export interface HeadingData {
    /** The plain text content of the heading. */
    content: string;
    /** The heading level. */
    level: number;
    /** Link to the heading. */
    link: string;
}
export interface NestedHeadingData {
    /** The heading content, level, and link. */
    heading: HeadingData;
    /** The sub-headings of this heading, if any. */
    children: NestedHeadingData[] | null;
}
/**
 * Takes a flat list of heading parameters and nests them based on each header's
 * immediate parent's level.
 *
 * @param headingList The flat list of headings to nest.
 *
 * @return The nested list of headings.
 */
export declare function linearToNestedHeadingList(headingList: HeadingData[]): NestedHeadingData[];
//# sourceMappingURL=utils.d.ts.map