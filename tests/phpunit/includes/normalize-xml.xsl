<?xml version='1.0' encoding='UTF-8' ?>
<!--
	Normalize an XML document to make it easier to compare whether 2 documents will
	be seen as "equal" to an XML processor.

	The normalization is similiar, in spirit, to {@link https://www.w3.org/TR/xml-c14n11/ Canonical XML},
	but without some aspects of C14N that make the kinds of assertions we need difficult.

	For example, the following XML documents will be interpreted the same by an XML processor,
	even though a string comparison of them would show differences:

	<root xmlns='urn:example'>
		<ns0:child xmlns:ns0='urn:another-example'>this is a test</ns0:child>
	</root>

	<ns0:root xmlns:ns0='urn:example'>
		<child xmlns='urn:another-example'>this is a test</child>
	</ns0:root>
  -->
<xsl:transform
		xmlns:xsl='http://www.w3.org/1999/XSL/Transform'
		version='1.0'
	>

	<!--
		Output UTF-8 XML, no indendation and all CDATA sections replaced with their character content. 
	  -->
	<xsl:output
		method='xml'
		indent='no'
		cdata-section-elements=''
		encoding='UTF-8' />

	<!--
		Strip insignificant white space.
	  -->
	<xsl:strip-space elements='*' />

	<!--
		Noramlize elements by not relying on the prefix used in the input document
		and ordering attributes first by namespace-uri and then by local-name.
	  -->
	<xsl:template match='*' priority='10'>
		<xsl:element name='{local-name()}' namespace='{namespace-uri()}'>
			<xsl:apply-templates select='@*'>
				<xsl:sort select='namespace-uri()' />
				<xsl:sort select='local-name()' />
			</xsl:apply-templates>

			<xsl:apply-templates select='node()' />
		</xsl:element>
	</xsl:template>

	<!--
		Noramlize attributes by not relying on the prefix used in the input document.
	  -->
	<xsl:template match='@*'>
		<xsl:attribute name='{local-name()}' namespace='{namespace-uri()}'>
			<xsl:value-of select='.' />
		</xsl:attribute>
	</xsl:template>

	<!--
		Strip comments. 
	  -->
	<xsl:template match='comment()' priority='10' />

	<!--
		Pass all other nodes through unchanged.  
	  -->
	<xsl:template match='node()'>
		<xsl:copy>
			<xsl:apply-templates select='node()' />
		</xsl:copy>
	</xsl:template>
</xsl:transform>
