const folderSelectors = {
    folderLT502: 'div.foldertree > .filemanager',
    folderGtEq502: 'ul.foldertree'
};

const folderFileSelectors = {
    folderFileItemLt502: `${folderSelectors.folderLT502} .ygtvitem`,
    folderFileItemGtEq502: `${folderSelectors.folderGtEq502} > li[role="treeitem"] ul[role="group"] li[role="treeitem"] > p`
};

const selectors = {
    ...folderSelectors,
    ...folderFileSelectors,
    folderFile: `${folderFileSelectors.folderFileItemLt502}, ${folderFileSelectors.folderFileItemGtEq502}`
};

const moodleVersion502 = 2026032700;

export const getSelectors = (moodleVersion) => {
    if (moodleVersion < moodleVersion502) {
        return {
            folder: selectors.folderLT502,
            folderFileItems: selectors.folderFileItemLt502,
            folderUnwrappedLinks: `${selectors.folderLT502} span:not(.filter-ally-wrapper) > a[href*="pluginfile.php"]`
        };
    } else {
        return {
            folder: selectors.folderGtEq502,
            folderFileItems: selectors.folderFileItemGtEq502,
            folderUnwrappedLinks: `${selectors.folderGtEq502} p > a[href*="pluginfile.php"]`
        };
    }
};
