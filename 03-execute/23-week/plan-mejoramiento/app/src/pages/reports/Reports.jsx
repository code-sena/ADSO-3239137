import { useEffect, useState } from "react";

import {
  ChartNoAxesCombined,
  FileBarChart,
  CalendarDays,
  RotateCcw,
  Users,
  Package,
  FileSpreadsheet,
  FileText,
  ShieldCheck,
  Download,
  RefreshCw
} from "lucide-react";

import ExcelJS from "exceljs";
import { saveAs } from "file-saver";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

import api from "../../services/api";
import reportsBanner from "../../assets/images/reports-banner.png";
import "./Reports.css";

const reportCategories = [
  {
    id: "loans",
    number: "01",
    title: "Loan Reports",
    description: "Review registered loans, active equipment and overdue items.",
    icon: CalendarDays,
    color: "blue",
    status: "Loan management"
  },
  {
    id: "returns",
    number: "02",
    title: "Return Reports",
    description: "Analyze returned technical aids and return history.",
    icon: RotateCcw,
    color: "orange",
    status: "Return management"
  },
  {
    id: "beneficiaries",
    number: "03",
    title: "Beneficiary Reports",
    description: "Review beneficiary records and their loan activity.",
    icon: Users,
    color: "green",
    status: "People management"
  },
  {
    id: "inventory",
    number: "04",
    title: "Inventory Reports",
    description: "Monitor registered aids and available units.",
    icon: Package,
    color: "purple",
    status: "Inventory management"
  }
];

const initialSummary = {
  loans_count: 0,
  returns_count: 0,
  beneficiaries_count: 0,
  technical_aids_count: 0,
  total_units: 0,
  available_units: 0,
  borrowed_units: 0
};

const reportDefinitions = [
  {
    key: "loans_count",
    label: "Loan Reports",
    description: "Registered loan records",
    icon: CalendarDays,
    color: "blue"
  },
  {
    key: "returns_count",
    label: "Return Reports",
    description: "Processed returns",
    icon: RotateCcw,
    color: "orange"
  },
  {
    key: "beneficiaries_count",
    label: "Beneficiary Reports",
    description: "Registered beneficiaries",
    icon: Users,
    color: "green"
  },
  {
    key: "technical_aids_count",
    label: "Inventory Reports",
    description: "Active technical aid records",
    icon: Package,
    color: "purple"
  }
];

function getReportRows(summary) {
  return [
    ["Registered loans", summary.loans_count],
    ["Processed returns", summary.returns_count],
    ["Registered beneficiaries", summary.beneficiaries_count],
    ["Active technical aids", summary.technical_aids_count],
    ["Total inventory units", summary.total_units],
    ["Available units", summary.available_units],
    ["Units currently borrowed", summary.borrowed_units]
  ];
}

function getDateLabel() {
  return new Date().toLocaleDateString("en-GB");
}

export default function Reports() {
  const [summary, setSummary] = useState(initialSummary);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState("");
  const [error, setError] = useState("");

  const loadSummary = async () => {
    setLoading(true);
    setError("");

    try {
      const response = await api.get("/reports/summary");
      setSummary({
        ...initialSummary,
        ...response.data
      });
    } catch (err) {
      console.error("Error loading report summary:", err);
      setError(
        err.response?.data?.message ||
        "Unable to load report information. Please try again."
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSummary();
  }, []);

  const exportExcel = async () => {
    try {
      setExporting("excel");

      const workbook = new ExcelJS.Workbook();
      workbook.creator = "Mobility Aids";
      workbook.created = new Date();

      const worksheet = workbook.addWorksheet("Management Report");

      worksheet.columns = [
        { header: "Indicator", key: "indicator", width: 34 },
        { header: "Value", key: "value", width: 18 }
      ];

      worksheet.addRows(
        getReportRows(summary).map(([indicator, value]) => ({
          indicator,
          value
        }))
      );

      worksheet.insertRow(1, ["MOBILITY AIDS - MANAGEMENT REPORT"]);
      worksheet.mergeCells("A1:B1");

      worksheet.getRow(1).font = {
        bold: true,
        size: 16,
        color: { argb: "FFFFFFFF" }
      };

      worksheet.getRow(1).height = 30;
      worksheet.getRow(1).alignment = {
        vertical: "middle",
        horizontal: "center"
      };

      worksheet.getRow(1).fill = {
        type: "pattern",
        pattern: "solid",
        fgColor: { argb: "FF14243A" }
      };

      worksheet.getRow(2).font = {
        bold: true,
        color: { argb: "FFFFFFFF" }
      };

      worksheet.getRow(2).fill = {
        type: "pattern",
        pattern: "solid",
        fgColor: { argb: "FFC69A43" }
      };

      worksheet.addRow([]);
      worksheet.addRow(["Generated on", getDateLabel()]);

      const buffer = await workbook.xlsx.writeBuffer();

      const blob = new Blob([buffer], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
      });

      saveAs(blob, `mobility-aids-report-${Date.now()}.xlsx`);
    } catch (err) {
      console.error("Excel export error:", err);
      window.alert("The Excel report could not be generated.");
    } finally {
      setExporting("");
    }
  };

  const exportPDF = () => {
    try {
      setExporting("pdf");

      const doc = new jsPDF();

      doc.setFillColor(20, 36, 58);
      doc.rect(0, 0, 210, 38, "F");

      doc.setTextColor(255, 255, 255);
      doc.setFontSize(19);
      doc.text("MOBILITY AIDS", 14, 17);

      doc.setFontSize(11);
      doc.text("Management & Analytics Report", 14, 27);

      doc.setTextColor(70, 70, 70);
      doc.setFontSize(10);
      doc.text(`Generated on: ${getDateLabel()}`, 14, 48);

      autoTable(doc, {
        startY: 57,
        head: [["Indicator", "Value"]],
        body: getReportRows(summary),
        theme: "grid",
        headStyles: {
          fillColor: [198, 154, 67],
          textColor: [255, 255, 255],
          fontStyle: "bold"
        },
        styles: {
          fontSize: 10,
          cellPadding: 5
        },
        alternateRowStyles: {
          fillColor: [245, 247, 250]
        }
      });

      doc.setFontSize(9);
      doc.setTextColor(120, 120, 120);
      doc.text(
        "Mobility Aids - Technical Aids Bank for Mobility",
        14,
        285
      );

      doc.save(`mobility-aids-report-${Date.now()}.pdf`);
    } catch (err) {
      console.error("PDF export error:", err);
      window.alert("The PDF report could not be generated.");
    } finally {
      setExporting("");
    }
  };

  return (
    <div className="reports-page">

      <section
        className="reports-hero"
        style={{ backgroundImage: `url(${reportsBanner})` }}
      >
        <div className="reports-hero-content">
          <div className="reports-hero-icon">
            <ChartNoAxesCombined size={38} strokeWidth={1.8} />
          </div>

          <div className="reports-hero-copy">
            <span>MANAGEMENT INSIGHTS</span>

            <h2>
              Reports & <strong>Analytics</strong>
            </h2>

            <p>
              Analyze loans, returns, beneficiaries and
              technical aid inventory in one place.
            </p>

            <div className="reports-hero-line"></div>
          </div>
        </div>
      </section>

      <section className="reports-stats">
        {reportDefinitions.map((stat) => {
          const Icon = stat.icon;

          return (
            <article
              className={`reports-stat reports-stat-${stat.color}`}
              key={stat.key}
            >
              <div className="reports-stat-icon">
                <Icon size={27} strokeWidth={1.9} />
              </div>

              <div className="reports-stat-content">
                <strong>
                  {loading ? "..." : summary[stat.key].toLocaleString()}
                </strong>

                <span>{stat.label}</span>
                <small>{stat.description}</small>
              </div>
            </article>
          );
        })}
      </section>

      {error && (
        <div className="reports-api-notice" role="alert">
          <span></span>
          {error}
          <button type="button" onClick={loadSummary}>
            <RefreshCw size={15} /> Retry
          </button>
        </div>
      )}

      <section className="reports-panel">
        <div className="reports-section-heading">
          <div>
            <h2>
              <FileBarChart size={23} />
              Report Categories
            </h2>

            <p>
              Explore the different reporting areas of Mobility Aids.
            </p>
          </div>

          <span className="reports-section-label">
            04 CATEGORIES
          </span>
        </div>

        <div className="reports-category-grid">
          {reportCategories.map((category) => {
            const Icon = category.icon;

            return (
              <article
                className={`reports-category-card reports-category-${category.color}`}
                key={category.id}
              >
                <div className="reports-category-top">
                  <div className="reports-category-icon">
                    <Icon size={25} strokeWidth={1.9} />
                  </div>

                  <span className="reports-category-number">
                    {category.number}
                  </span>
                </div>

                <h3>{category.title}</h3>
                <p>{category.description}</p>

                <div className="reports-category-footer">
                  <span className="reports-category-dot"></span>
                  {category.status}
                </div>
              </article>
            );
          })}
        </div>
      </section>

      <section className="reports-generation-panel">
        <div className="reports-generation-heading">
          <div className="reports-generation-icon">
            <FileBarChart size={26} />
          </div>

          <div>
            <h2>Report Generation</h2>
            <p>
              Prepare and export management summary reports.
            </p>
          </div>
        </div>

        <div className="reports-generation-content">
          <div className="reports-generation-message">
            <ShieldCheck size={21} />

            <div>
              <strong>Centralized reporting</strong>
              <p>
                Report figures are retrieved directly from the
                Mobility Aids database.
              </p>
            </div>
          </div>

          <div className="reports-export-options">
            <button
              type="button"
              className="reports-export-item"
              onClick={exportExcel}
              disabled={loading || Boolean(error) || Boolean(exporting)}
            >
              <FileSpreadsheet size={22} />

              <div>
                <strong>
                  {exporting === "excel" ? "Generating..." : "Excel"}
                </strong>
                <small>Download .xlsx spreadsheet</small>
              </div>

              <Download size={17} />
            </button>

            <button
              type="button"
              className="reports-export-item"
              onClick={exportPDF}
              disabled={loading || Boolean(error) || Boolean(exporting)}
            >
              <FileText size={22} />

              <div>
                <strong>
                  {exporting === "pdf" ? "Generating..." : "PDF"}
                </strong>
                <small>Download PDF document</small>
              </div>

              <Download size={17} />
            </button>
          </div>
        </div>

        <div className="reports-api-notice">
          <span></span>
          {loading
            ? "Loading report information..."
            : error
              ? "Report information could not be synchronized."
              : "Report information successfully synchronized with the API."}
        </div>
      </section>

      <footer className="reports-footer">
        <span>Mobility Aids · Management & Analytics</span>
        <span>Technical Aids Bank for Mobility</span>
      </footer>

    </div>
  );
}
