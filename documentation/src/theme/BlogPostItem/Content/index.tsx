import React, { type ReactNode } from 'react';
import { useBlogPost } from '@docusaurus/plugin-content-blog/client';
import Content from '@theme-original/BlogPostItem/Content';
import type { Props } from '@theme/BlogPostItem/Content';

export default function BlogPostItemContent(props: Props): ReactNode {
    const { frontMatter, isBlogPostPage } = useBlogPost();
    const preview = 'release_preview' in frontMatter ? frontMatter.release_preview : undefined;

    return (
        <Content {...props}>
            {!isBlogPostPage && typeof preview === 'string'
                ? <p>{preview}</p>
                : props.children}
        </Content>
    );
}
