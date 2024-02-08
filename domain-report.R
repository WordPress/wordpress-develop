library(ggplot2)
library(dplyr)
library(patchwork)

data <- read.csv('./domain-report.csv')
data$mbps.tags = ( data$bytes / 1e6 ) / ( data$tag.time.in.nanoseconds / 1e9 )
data$mbps.html = ( data$bytes / 1e6 ) / ( data$time.in.nanoseconds / 1e9 )
data$mem.ratio = data$html.mem / data$tags.mem
data$ms.tags   = data$tag.time.in.nanoseconds / 1e6
data$ms.html   = data$time.in.nanoseconds / 1e6

plot <- ggplot(filter(data, tag.count < 10000, success == 'success'))

chart <- (
    ( plot
            + geom_histogram(bins=200, aes(ratio), alpha=0.7)
            + xlab('Ratio of runtime: HTML vs. Tags')
            + scale_x_log10(limits=c(0.1, 100), breaks=c(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100))
    ) +
    ( plot
            + geom_histogram(bins=200, aes(ms.tags))
            + xlab('ms runtime: Tags')
            + scale_x_log10(limits=c(1, 15), breaks=seq(1, 15, 3))
    ) +
    ( plot
            + geom_histogram(bins=200, aes(ms.html))
            + xlab('ms runtime: HTML')
            + scale_x_log10(limits=c(1, 90), breaks=seq(1, 80, 10))
    )
) / (
    ( plot
        + geom_point(aes(x=tag.count, y=ratio))
        + xlab('tags in input HTML')
        + ylab('runtime ratio')
        + scale_x_log10()
    ) +
    (plot
        + geom_point(aes(x=tag.count, y=ms.html))
        + xlab('tags in input HTML')
        + ylab('ms runtime: HTML')
        + scale_x_log10()
    )
) / (
    (plot
        + geom_histogram(bins=200, aes(mbps.tags))
        + xlab('MBps Tags')
        + scale_x_continuous(limits=c(0, 100), breaks=seq(0, 100, 10))
    ) +
    (plot
        + geom_histogram(bins=200, aes(mbps.html))
        + xlab('MBps HTML')
#         + scale_x_continuous(limits=c(0, 40), breaks=seq(0, 40, 5))
		+ scale_x_log10(limits=c(1, 40), breaks=seq(1, 40, 2))
    )
) / (
	ggplot(filter(data, tag.count < 10000, success == 'success'))
		+ geom_histogram(bins=200, aes(mem.ratio))
		+ xlab('Ratio of runtime memory use: HTML vs. Tags')
		+ scale_x_continuous(limits=c(-5, 5))
)

ggsave('./domain-report-chart.png', plot=chart, width=20, height=10, units='in', dpi=300)
