import { StyleSheet, Text, View } from 'react-native';

import { colors, spacing, typography } from '../theme';

export interface BarSeries {
  key: string;
  label: string;
  color: string;
}

interface Props {
  /** One label per column, e.g. month names. */
  labels: string[];
  series: BarSeries[];
  /** values[column][seriesKey] */
  values: Record<string, number>[];
  /** "stacked" piles the series in one bar; "grouped" puts them side by side. */
  mode?: 'stacked' | 'grouped';
  height?: number;
  /** Text above each column, e.g. "$12". */
  formatTotal?: (value: number) => string;
}

/** A small bar chart drawn with plain views, so it needs no chart library. */
export function BarChart({ labels, series, values, mode = 'stacked', height = 120, formatTotal = String }: Props) {
  const totals = values.map((column) => series.reduce((sum, s) => sum + (column[s.key] ?? 0), 0));
  const tallest = Math.max(1, ...(mode === 'stacked' ? totals : values.flatMap((column) => series.map((s) => column[s.key] ?? 0))));

  return (
    <View>
      <View style={[styles.plot, { height: height + 18 }]}>
        {values.map((column, index) => (
          <View key={labels[index]} style={styles.column} accessibilityLabel={`${labels[index]}: ${series.map((s) => `${s.label} ${formatTotal(column[s.key] ?? 0)}`).join(', ')}`}>
            <Text style={styles.total}>{totals[index] > 0 ? formatTotal(totals[index]) : ''}</Text>
            <View style={[styles.bars, mode === 'grouped' && styles.grouped]}>
              {(mode === 'stacked' ? [...series].reverse() : series).map((s) => {
                const value = column[s.key] ?? 0;

                return value > 0 ? (
                  <View
                    key={s.key}
                    style={[
                      mode === 'stacked' ? styles.segment : styles.side,
                      { height: (value / tallest) * height, backgroundColor: s.color },
                    ]}
                  />
                ) : mode === 'grouped' ? (
                  <View key={s.key} style={styles.side} />
                ) : null;
              })}
            </View>
          </View>
        ))}
      </View>
      <View style={styles.labels}>
        {labels.map((label) => (
          <Text key={label} style={[typography.caption, styles.label]}>
            {label}
          </Text>
        ))}
      </View>
      {series.length > 1 ? (
        <View style={styles.legend}>
          {series.map((s) => (
            <View key={s.key} style={styles.legendItem}>
              <View style={[styles.swatch, { backgroundColor: s.color }]} />
              <Text style={typography.caption}>{s.label}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  plot: { flexDirection: 'row', alignItems: 'flex-end', gap: spacing.sm, borderBottomWidth: 1, borderColor: colors.border },
  column: { flex: 1, alignItems: 'center', justifyContent: 'flex-end' },
  total: { fontSize: 11, color: colors.textMuted, marginBottom: 2 },
  bars: { width: '70%', justifyContent: 'flex-end' },
  grouped: { flexDirection: 'row', alignItems: 'flex-end', gap: 2 },
  segment: { width: '100%' },
  side: { flex: 1 },
  labels: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.xs },
  label: { flex: 1, textAlign: 'center' },
  legend: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md, marginTop: spacing.sm },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
  swatch: { width: 10, height: 10, borderRadius: 2 },
});
