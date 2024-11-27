<?php

declare(strict_types=1);

namespace Smic\DynamicRoutingPages;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationModifier
{
    /**
     * @var array<string, array<int>>
     */
    protected static $cache = [];

    public static function modifyConfiguration(array $configuration): array
    {
        foreach ($configuration as $siteKey => $siteConfiguration) {
            $configuration[$siteKey] = self::modifySiteConfiguration($siteConfiguration);
        }
        return $configuration;
    }

    public static function modifySiteConfiguration(array $siteConfiguration): array
    {
        if (!isset($siteConfiguration['routeEnhancers'])) {
            return $siteConfiguration;
        }

        foreach ($siteConfiguration['routeEnhancers'] as $key => $enhancerConfiguration) {
            if (!isset($enhancerConfiguration['dynamicPages'])) {
                continue;
            }
            $enhancerConfiguration['limitToPages'] = self::findDynamicPages($enhancerConfiguration['dynamicPages']);
            $siteConfiguration['routeEnhancers'][$key] = $enhancerConfiguration;
        }
        return $siteConfiguration;
    }

    protected static function findDynamicPages(array $dynamicPagesConfiguration): array
    {
        $pageUids = [];
        if (isset($dynamicPagesConfiguration['withCType'])) {
            $withCType = is_array($dynamicPagesConfiguration['withCType']) ? $dynamicPagesConfiguration['withCType'] : [$dynamicPagesConfiguration['withCType']];
            $withCTypeCacheKey = sha1(json_encode($withCType));
            self::$cache[$withCTypeCacheKey] = self::$cache[$withCTypeCacheKey] ?? self::findPagesWithCType($withCType);
            array_push($pageUids, ...self::$cache[$withCTypeCacheKey]);
        }
        if (isset($dynamicPagesConfiguration['withPlugin'])) {
            $withPlugins = is_array($dynamicPagesConfiguration['withPlugin']) ? $dynamicPagesConfiguration['withPlugin'] : [$dynamicPagesConfiguration['withPlugin']];
            $withPluginsCacheKey = sha1(json_encode($withPlugins));
            self::$cache[$withPluginsCacheKey] = self::$cache[$withPluginsCacheKey] ?? self::findPagesWithPlugins($withPlugins);
            array_push($pageUids, ...self::$cache[$withPluginsCacheKey]);
        }
        if (isset($dynamicPagesConfiguration['withDoktypes'])) {
            $withDoktypes = is_array($dynamicPagesConfiguration['withDoktypes']) ? $dynamicPagesConfiguration['withDoktypes'] : [$dynamicPagesConfiguration['withDoktypes']];
            $withDoktypesCacheKey = sha1(json_encode($withDoktypes));
            self::$cache[$withDoktypesCacheKey] = self::$cache[$withDoktypesCacheKey] ?? self::findPagesWithDoktypes($withDoktypes);
            array_push($pageUids, ...self::$cache[$withDoktypesCacheKey]);
        }
        if (isset($dynamicPagesConfiguration['containsModule'])) {
            $containsModules = is_array($dynamicPagesConfiguration['containsModule']) ? $dynamicPagesConfiguration['containsModule'] : [$dynamicPagesConfiguration['containsModule']];
            $containsModulesCacheKey = sha1(json_encode($containsModules));
            self::$cache[$containsModulesCacheKey] = self::$cache[$containsModulesCacheKey] ?? self::findPagesContainingModules($containsModules);
            array_push($pageUids, ...self::$cache[$containsModulesCacheKey]);
        }

        return array_unique($pageUids);
    }

    protected static function findPagesWithCType(array $configuration): array
    {
        [$types, $flexFormRestrictions] = self::extractPluginConfiguration($configuration);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder = $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in('CType', $queryBuilder->createNamedParameter($types, Connection::PARAM_STR_ARRAY))
            );
        if (!empty($flexFormRestrictions)) {
            self::addFlexFormRestrictions($queryBuilder, $flexFormRestrictions);
        }

        return $queryBuilder->executeQuery()
            ->fetchFirstColumn();
    }

    protected static function findPagesWithPlugins(array $configuration): array
    {
        [$types, $flexFormRestrictions] = self::extractPluginConfiguration($configuration);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder = $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list', Connection::PARAM_STR)),
                $queryBuilder->expr()->in('list_type', $queryBuilder->createNamedParameter($types, Connection::PARAM_STR_ARRAY))
            );
        if (!empty($flexFormRestrictions)) {
            self::addFlexFormRestrictions($queryBuilder, $flexFormRestrictions);
        }

        return $queryBuilder->executeQuery()
            ->fetchFirstColumn();
    }

    protected static function findPagesContainingModules(array $modules): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $pageRecords = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('module', $queryBuilder->createNamedParameter($modules, Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return $pageRecords;
    }

    protected static function findPagesWithDoktypes(array $doktypes): array
    {
        $doktypes = array_map('intval', $doktypes);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $pageRecords = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in(
                  'doktype',
                  $queryBuilder->createNamedParameter($doktypes, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return $pageRecords;
    }

    protected static function extractPluginConfiguration(array $configuration): array
    {
        /**
         * Configuration is either more complex with flexFormRestrictions:
         *
         * withCType:
         *   identifiers:
         *     - news_pi1
         *   flexFormRestrictions:
         *     - field: settings.eventRestriction
         *     value: '1'
         *
         * or a simple string or list of CType identifiers:
         *
         * withCType: news_pi1
         */
        $types = $configuration;
        $flexFormRestrictions = [];
        if (isset($configuration['types'])) {
            $types = $configuration['types'];
            $flexFormRestrictions = $configuration['flexFormRestrictions'] ?? [];
        }

        return [$types, $flexFormRestrictions];
    }

    protected static function addFlexFormRestrictions(QueryBuilder $queryBuilder, array $flexFormRestrictions): void
    {
        $constraints = [];
        foreach ($flexFormRestrictions as $restriction) {
            if (!isset($restriction['field']) || !isset($restriction['value'])) {
                continue;
            }

            $xmlPattern = self::buildFlexFormXmlPattern(
                $restriction['field'],
                $restriction['value']
            );
            $constraints[] = $queryBuilder->expr()->like(
                'pi_flexform',
                $queryBuilder->createNamedParameter($xmlPattern, Connection::PARAM_STR)
            );
        }

        if (!empty($constraints)) {
            $queryBuilder->andWhere(...$constraints);
        }
    }

    protected static function buildFlexFormXmlPattern(string $fieldPath, string $value): string
    {
        return '%<field index="' . $fieldPath . '">%'
            . '<value index="vDEF">' . $value . '</value>%';
    }
}
